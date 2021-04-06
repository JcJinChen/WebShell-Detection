<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\PropertyAccess\Exception\InvalidArgumentException;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

/**
 * Base class for a normalizer dealing with objects.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
abstract class AbstractObjectNormalizer extends AbstractNormalizer
{
    const ENABLE_MAX_DEPTH = 'enable_max_depth';
    const DEPTH_KEY_PATTERN = 'depth_%s::%s';
    const DISABLE_TYPE_ENFORCEMENT = 'disable_type_enforcement';
    const SKIP_NULL_VALUES = 'skip_null_values';

    private $propertyTypeExtractor;
    private $typesCache = array();
    private $attributesCache = array();

    /**
     * @var callable|null
     */
    private $maxDepthHandler;
    private $objectClassResolver;

    /**
     * @var ClassDiscriminatorResolverInterface|null
     */
    protected $classDiscriminatorResolver;

    public function __construct(ClassMetadataFactoryInterface $classMetadataFactory = null, NameConverterInterface $nameConverter = null, PropertyTypeExtractorInterface $propertyTypeExtractor = null, ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null, callable $objectClassResolver = null)
    {
        parent::__construct($classMetadataFactory, $nameConverter);

        $this->propertyTypeExtractor = $propertyTypeExtractor;

        if (null === $classDiscriminatorResolver && null !== $classMetadataFactory) {
            $classDiscriminatorResolver = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
        }
        $this->classDiscriminatorResolver = $classDiscriminatorResolver;
        $this->objectClassResolver = $objectClassResolver;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return \is_object($data) && !$data instanceof \Traversable;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if (!isset($context['cache_key'])) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        if ($this->isCircularReference($object, $context)) {
            return $this->handleCircularReference($object, $format, $context);
        }

        $data = array();
        $stack = array();
        $attributes = $this->getAttributes($object, $format, $context);
        $class = $this->objectClassResolver ? \call_user_func($this->objectClassResolver, $object) : \get_class($object);
        $attributesMetadata = $this->classMetadataFactory ? $this->classMetadataFactory->getMetadataFor($class)->getAttributesMetadata() : null;

        foreach ($attributes as $attribute) {
            $maxDepthReached = false;
            if (null !== $attributesMetadata && ($maxDepthReached = $this->isMaxDepthReached($attributesMetadata, $class, $attribute, $context)) && !$this->maxDepthHandler) {
                continue;
            }

            $attributeValue = $this->getAttributeValue($object, $attribute, $format, $context);
            if ($maxDepthReached) {
                $attributeValue = \call_user_func($this->maxDepthHandler, $attributeValue, $object, $attribute, $format, $context);
            }

            if (isset($this->callbacks[$attribute])) {
                $attributeValue = \call_user_func($this->callbacks[$attribute], $attributeValue, $object, $attribute, $format, $context);
            }

            if (null !== $attributeValue && !is_scalar($attributeValue)) {
                $stack[$attribute] = $attributeValue;
            }

            $data = $this->updateData($data, $attribute, $attributeValue, $class, $format, $context);
        }

        foreach ($stack as $attribute => $attributeValue) {
            if (!$this->serializer instanceof NormalizerInterface) {
                throw new LogicException(sprintf('Cannot normalize attribute "%s" because the injected serializer is not a normalizer', $attribute));
            }

            $data = $this->updateData($data, $attribute, $this->serializer->normalize($attributeValue, $format, $this->createChildContext($context, $attribute)), $class, $format, $context);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function instantiateObject(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null)
    {
        if ($this->classDiscriminatorResolver && $mapping = $this->classDiscriminatorResolver->getMappingForClass($class)) {
            if (!isset($data[$mapping->getTypeProperty()])) {
                throw new RuntimeException(sprintf('Type property "%s" not found for the abstract object "%s"', $mapping->getTypeProperty(), $class));
            }

            $type = $data[$mapping->getTypeProperty()];
            if (null === ($mappedClass = $mapping->getClassForType($type))) {
                throw new RuntimeException(sprintf('The type "%s" has no mapped class for the abstract object "%s"', $type, $class));
            }

            $class = $mappedClass;
            $reflectionClass = new \ReflectionClass($class);
        }

        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }

    /**
     * Gets and caches attributes for the given object, format and context.
     *
     * @param object      $object
     * @param string|null $format
     * @param array       $context
     *
     * @return string[]
     */
    protected function getAttributes($object, $format = null, array $context)
    {
        $class = $this->objectClassResolver ? \call_user_func($this->objectClassResolver, $object) : \get_class($object);
        $key = $class.'-'.$context['cache_key'];

        if (isset($this->attributesCache[$key])) {
            return $this->attributesCache[$key];
        }

        $allowedAttributes = $this->getAllowedAttributes($object, $context, true);

        if (false !== $allowedAttributes) {
            if ($context['cache_key']) {
                $this->attributesCache[$key] = $allowedAttributes;
            }

            return $allowedAttributes;
        }

        if (isset($context['attributes'])) {
            return $this->extractAttributes($object, $format, $context);
        }

        if (isset($this->attributesCache[$class])) {
            return $this->attributesCache[$class];
        }

        $attributes = $this->extractAttributes($object, $format, $context);

        if ($this->classDiscriminatorResolver && $mapping = $this->classDiscriminatorResolver->getMappingForMappedObject($object)) {
            array_unshift($attributes, $mapping->getTypeProperty());
        }

        return $this->attributesCache[$class] = $attributes;
    }

    /**
     * Extracts attributes to normalize from the class of the given object, format and context.
     *
     * @param object      $object
     * @param string|null $format
     * @param array       $context
     *
     * @return string[]
     */
    abstract protected function extractAttributes($object, $format = null, array $context = array());

    /**
     * Gets the attribute value.
     *
     * @param object      $object
     * @param string      $attribute
     * @param string|null $format
     * @param array       $context
     *
     * @return mixed
     */
    abstract protected function getAttributeValue($object, $attribute, $format = null, array $context = array());

    /**
     * Sets a handler function that will be called when the max depth is reached.
     */
    public function setMaxDepthHandler(?callable $handler): void
    {
        $this->maxDepthHandler = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return \class_exists($type) || (\interface_exists($type, false) && $this->classDiscriminatorResolver && null !== $this->classDiscriminatorResolver->getMappingForClass($type));
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        if (!isset($context['cache_key'])) {
            $context['cache_key'] = $this->getCacheKey($format, $context);
        }

        $allowedAttributes = $this->getAllowedAttributes($class, $context, true);
        $normalizedData = $this->prepareForDenormalization($data);
        $extraAttributes = array();

        $reflectionClass = new \ReflectionClass($class);
        $object = $this->instantiateObject($normalizedData, $class, $context, $reflectionClass, $allowedAttributes, $format);

        foreach ($normalizedData as $attribute => $value) {
            if ($this->nameConverter) {
                $attribute = $this->nameConverter->denormalize($attribute, $class, $format, $context);
            }

            if ((false !== $allowedAttributes && !\in_array($attribute, $allowedAttributes)) || !$this->isAllowedAttribute($class, $attribute, $format, $context)) {
                if (isset($context[self::ALLOW_EXTRA_ATTRIBUTES]) && !$context[self::ALLOW_EXTRA_ATTRIBUTES]) {
                    $extraAttributes[] = $attribute;
                }

                continue;
            }

            $value = $this->validateAndDenormalize($class, $attribute, $value, $format, $context);
            try {
                $this->setAttributeValue($object, $attribute, $value, $format, $context);
            } catch (InvalidArgumentException $e) {
                throw new NotNormalizableValueException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if (!empty($extraAttributes)) {
            throw new ExtraAttributesException($extraAttributes);
        }

        return $object;
    }

    /**
     * Sets attribute value.
     *
     * @param object      $object
     * @param string      $attribute
     * @param mixed       $value
     * @param string|null $format
     * @param array       $context
     */
    abstract protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = array());

    /**
     * Validates the submitted data and denormalizes it.
     *
     * @param mixed $data
     *
     * @return mixed
     *
     * @throws NotNormalizableValueException
     * @throws LogicException
     */
    private function validateAndDenormalize(string $currentClass, string $attribute, $data, ?string $format, array $context)
    {
        if (null === $types = $this->getTypes($currentClass, $attribute)) {
            return $data;
        }

        $expectedTypes = array();
        foreach ($types as $type) {
            if (null === $data && $type->isNullable()) {
                return;
            }

            if ($type->isCollection() && null !== ($collectionValueType = $type->getCollectionValueType()) && Type::BUILTIN_TYPE_OBJECT === $collectionValueType->getBuiltinType()) {
                $builtinType = Type::BUILTIN_TYPE_OBJECT;
                $class = $collectionValueType->getClassName().'[]';

                // Fix a collection that contains the only one element
                // This is special to xml format only
                if ('xml' === $format && !\is_int(key($data))) {
                    $data = array($data);
                }

                if (null !== $collectionKeyType = $type->getCollectionKeyType()) {
                    $context['key_type'] = $collectionKeyType;
                }
            } else {
                $builtinType = $type->getBuiltinType();
                $class = $type->getClassName();
            }

            $expectedTypes[Type::BUILTIN_TYPE_OBJECT === $builtinType && $class ? $class : $builtinType] = true;

            if (Type::BUILTIN_TYPE_OBJECT === $builtinType) {
                if (!$this->serializer instanceof DenormalizerInterface) {
                    throw new LogicException(sprintf('Cannot denormalize attribute "%s" for class "%s" because injected serializer is not a denormalizer', $attribute, $class));
                }

                $childContext = $this->createChildContext($context, $attribute);
                if ($this->serializer->supportsDenormalization($data, $class, $format, $childContext)) {
                    return $this->serializer->denormalize($data, $class, $format, $childContext);
                }
            }

            // JSON only has a Number type corresponding to both int and float PHP types.
            // PHP's json_encode, JavaScript's JSON.stringify, Go's json.Marshal as well as most other JSON encoders convert
            // floating-point numbers like 12.0 to 12 (the decimal part is dropped when possible).
            // PHP's json_decode automatically converts Numbers without a decimal part to integers.
            // To circumvent this behavior, integers are converted to floats when denormalizing JSON based formats and when
            // a float is expected.
            if (Type::BUILTIN_TYPE_FLOAT === $builtinType && \is_int($data) && false !== strpos($format, JsonEncoder::FORMAT)) {
                return (float) $data;
            }

            if (\call_user_func('is_'.$builtinType, $data)) {
                return $data;
            }
        }

        if (!empty($context[self::DISABLE_TYPE_ENFORCEMENT])) {
            return $data;
        }

        throw new NotNormalizableValueException(sprintf('The type of the "%s" attribute for class "%s" must be one of "%s" ("%s" given).', $attribute, $currentClass, implode('", "', array_keys($expectedTypes)), \gettype($data)));
    }

    /**
     * @return Type[]|null
     */
    private function getTypes(string $currentClass, string $attribute)
    {
        if (null === $this->propertyTypeExtractor) {
            return null;
        }

        $key = $currentClass.'::'.$attribute;
        if (isset($this->typesCache[$key])) {
            return false === $this->typesCache[$key] ? null : $this->typesCache[$key];
        }

        if (null !== $types = $this->propertyTypeExtractor->getTypes($currentClass, $attribute)) {
            return $this->typesCache[$key] = $types;
        }

        if (null !== $this->classDiscriminatorResolver && null !== $discriminatorMapping = $this->classDiscriminatorResolver->getMappingForClass($currentClass)) {
            if ($discriminatorMapping->getTypeProperty() === $attribute) {
                return $this->typesCache[$key] = array(
                    new Type(Type::BUILTIN_TYPE_STRING),
                );
            }

            foreach ($discriminatorMapping->getTypesMapping() as $mappedClass) {
                if (null !== $types = $this->propertyTypeExtractor->getTypes($mappedClass, $attribute)) {
                    return $this->typesCache[$key] = $types;
                }
            }
        }

        $this->typesCache[$key] = false;

        return null;
    }

    /**
     * Sets an attribute and apply the name converter if necessary.
     *
     * @param mixed $attributeValue
     */
    private function updateData(array $data, string $attribute, $attributeValue, string $class, ?string $format, array $context): array
    {
        if (null === $attributeValue && ($context[self::SKIP_NULL_VALUES] ?? false)) {
            return $data;
        }

        if ($this->nameConverter) {
            $attribute = $this->nameConverter->normalize($attribute, $class, $format, $context);
        }

        $data[$attribute] = $attributeValue;

        return $data;
    }

    /**
     * Is the max depth reached for the given attribute?
     *
     * @param AttributeMetadataInterface[] $attributesMetadata
     */
    private function isMaxDepthReached(array $attributesMetadata, string $class, string $attribute, array &$context): bool
    {
        if (
            !isset($context[static::ENABLE_MAX_DEPTH]) ||
            !$context[static::ENABLE_MAX_DEPTH] ||
            !isset($attributesMetadata[$attribute]) ||
            null === $maxDepth = $attributesMetadata[$attribute]->getMaxDepth()
        ) {
            return false;
        }

        $key = sprintf(static::DEPTH_KEY_PATTERN, $class, $attribute);
        if (!isset($context[$key])) {
            $context[$key] = 1;

            return false;
        }

        if ($context[$key] === $maxDepth) {
            return true;
        }

        ++$context[$key];

        return false;
    }

    /**
     * Gets the cache key to use.
     *
     * @return bool|string
     */
    private function getCacheKey(?string $format, array $context)
    {
        try {
            return md5($format.serialize($context));
        } catch (\Exception $exception) {
            // The context cannot be serialized, skip the cache
            return false;
        }
    }
}
