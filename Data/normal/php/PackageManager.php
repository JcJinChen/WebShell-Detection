<?php

/*
 * This file is part of Packagist.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *     Nils Adermann <naderman@naderman.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Packagist\WebBundle\Model;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Packagist\WebBundle\Entity\Package;
use Psr\Log\LoggerInterface;
use AlgoliaSearch\Client as AlgoliaClient;
use Packagist\WebBundle\Service\GitHubUserMigrationWorker;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class PackageManager
{
    protected $doctrine;
    protected $mailer;
    protected $twig;
    protected $logger;
    protected $options;
    protected $providerManager;
    protected $algoliaClient;
    protected $algoliaIndexName;
    protected $githubWorker;

    public function __construct(RegistryInterface $doctrine, \Swift_Mailer $mailer, \Twig_Environment $twig, LoggerInterface $logger, array $options, ProviderManager $providerManager, AlgoliaClient $algoliaClient, string $algoliaIndexName, GitHubUserMigrationWorker $githubWorker)
    {
        $this->doctrine = $doctrine;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->options = $options;
        $this->providerManager = $providerManager;
        $this->algoliaClient = $algoliaClient;
        $this->algoliaIndexName  = $algoliaIndexName;
        $this->githubWorker  = $githubWorker;
    }

    public function deletePackage(Package $package)
    {
        /** @var VersionRepository $versionRepo */
        $versionRepo = $this->doctrine->getRepository('PackagistWebBundle:Version');
        foreach ($package->getVersions() as $version) {
            $versionRepo->remove($version);
        }

        if ($package->getAutoUpdated() === Package::AUTO_GITHUB_HOOK) {
            foreach ($package->getMaintainers() as $maintainer) {
                $token = $maintainer->getGithubToken();
                if ($token && $this->githubWorker->deleteWebHook($token, $package)) {
                    break;
                }
            }
        }

        $downloadRepo = $this->doctrine->getRepository('PackagistWebBundle:Download');
        $downloadRepo->deletePackageDownloads($package);

        $this->providerManager->deletePackage($package);
        $packageName = $package->getName();

        $em = $this->doctrine->getManager();
        $em->remove($package);
        $em->flush();

        // attempt search index cleanup
        try {
            $indexName = $this->algoliaIndexName;
            $algolia = $this->algoliaClient;
            $index = $algolia->initIndex($indexName);
            $index->deleteObject($packageName);
        } catch (\AlgoliaSearch\AlgoliaException $e) {
        }
    }

    public function notifyUpdateFailure(Package $package, \Exception $e, $details = null)
    {
        if (!$package->isUpdateFailureNotified()) {
            $recipients = array();
            foreach ($package->getMaintainers() as $maintainer) {
                if ($maintainer->isNotifiableForFailures()) {
                    $recipients[$maintainer->getEmail()] = $maintainer->getUsername();
                }
            }

            if ($recipients) {
                $body = $this->twig->render('PackagistWebBundle:Email:update_failed.txt.twig', array(
                    'package' => $package,
                    'exception' => get_class($e),
                    'exceptionMessage' => $e->getMessage(),
                    'details' => strip_tags($details),
                ));

                $message = \Swift_Message::newInstance()
                    ->setSubject($package->getName().' failed to update, invalid composer.json data')
                    ->setFrom($this->options['from'], $this->options['fromName'])
                    ->setTo($recipients)
                    ->setBody($body)
                ;

                try {
                    $this->mailer->send($message);
                } catch (\Swift_TransportException $e) {
                    $this->logger->error('['.get_class($e).'] '.$e->getMessage());

                    return false;
                }
            }

            $package->setUpdateFailureNotified(true);
            $this->doctrine->getEntityManager()->flush();
        }

        return true;
    }

    public function notifyNewMaintainer($user, $package)
    {
        $body = $this->twig->render('PackagistWebBundle:Email:maintainer_added.txt.twig', array(
            'package_name' => $package->getName()
        ));

        $message = \Swift_Message::newInstance()
            ->setSubject('You have been added to ' . $package->getName() . ' as a maintainer')
            ->setFrom($this->options['from'], $this->options['fromName'])
            ->setTo($user->getEmail())
            ->setBody($body)
        ;

        try {
            $this->mailer->send($message);
        } catch (\Swift_TransportException $e) {
            $this->logger->error('['.get_class($e).'] '.$e->getMessage());

            return false;
        }

        return true;
    }
}
