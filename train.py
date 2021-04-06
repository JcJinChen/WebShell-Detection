import os
import pandas as pd
import random
import warnings
warnings.filterwarnings('ignore')

from sklearn.feature_extraction.text import CountVectorizer
from sklearn.model_selection import train_test_split
from sklearn.naive_bayes import GaussianNB
from sklearn import metrics
from sklearn.feature_extraction.text import TfidfTransformer

import pickle
import chardet

max_features = 50000
nor_web = {0: "normalfile", 1:"webshell"}

webshell_dir = "Data/WebShell/php/"
normal_dir = "Data/normal/php/"

white_count = 0
black_count = 0


def check_style(filepath):
    with open(filepath, mode='rb') as f:
        data = f.read()
        style = chardet.detect(data)['encoding']
        return style


def load_str(filepath):
    t = ""
    try:
        style = check_style(filepath)
        with open(filepath, encoding=style) as f:
            for line in f:
                line = line.strip('\r')
                line = line.strip('\n')
                t += line
    except UnicodeDecodeError:
        with open(filepath, mode='rb') as f:
            t = f.read()

    return t

def rand_filepath(dir, sample_num):
    """
    得到sample文件路径的随机化列表，以及剩余样本的路径名列表
    :param dir:webshell样本或者正常样本文件夹路径
    :param sample_num:   样本数,限定训练样本数
    :return: sample文件路径的随机化列表
    """
    # os.walk()  返回的是三元组(root, dirs, files)
    # root 表示当前正在遍历的这个文件夹的本身地址
    # dirs 为list，该文件夹中所有的目录的名字（不包括子目录）
    # files同样为list，该文件夹中所有文件（不包括子目录）
    sample_files_path_list = []
    g = os.walk(dir)
    for path, d, filelist in g:
        for filename in filelist:
            # endswith()方法的返回值为bool型，用于判断字符串是否以给定字符结尾
            if filename.endswith('.php'):
                fulpath = os.path.join(path, filename)
                # print("Load %s" % fulpath)
                # t = load_str(fulpath)
                sample_files_path_list.append(fulpath)
    # n_total = len(full_list)
    # offset = int(n_total * ratio)
    # if n_total == 0 or offset < 1:
    #     return [], full_list
    random.shuffle(sample_files_path_list)
    train_list = sample_files_path_list[:sample_num]
    res_list = sample_files_path_list[sample_num:]
    return train_list, res_list

def load_files(dir, sample_num):
    files_list = []
    res_files_list = []
    files_path, res_files_path = rand_filepath(dir, sample_num)
    for filename in files_path:
        print("Load %s" % filename)
        t = load_str(filename)
        files_list.append(t)
    for filename in res_files_path:
        print("Load %s" % filename)
        t = load_str(filename)
        res_files_list.append(t)
    return  files_path,files_list, res_files_path, res_files_list

# def count_sample():
# 获取样本数量


def get_feature_by_wordbag_tfidf():
    global max_features
    # global white_count
    # global black_count
    print ("max_features = %d" % max_features)
    # webshell_num = count_sample()
    webshell_num = 1000
    rate = 3
    webshellfile_name_list, webshell_files_list, res_webshellfile_name_list, res_webshell_files_list = load_files(webshell_dir, webshell_num)
    y1 = [1] * webshell_num
    # black_count = len(webshell_files_list)
    normal_num = webshell_num * rate
    normalfilename_list, normal_files_list, res_normalfilename_list, res_normal_files_list = load_files(normal_dir, normal_num)
    # print("the length of normalfilename_list id %d"%len(normalfilename_list))
    # print(normalfilename_list)
    # print("the length of normal_files_list id %d" % len(normal_files_list))
    y2 = [0] * normal_num
    # white_count = len(normal_files_list)


    x = webshell_files_list[:webshell_num] + normal_files_list[:normal_num]
    filename_index = webshellfile_name_list[:webshell_num] + normalfilename_list[:normal_num]
    y = y1 + y2

    CV = CountVectorizer(ngram_range = (2, 2), decode_error = 'ignore', max_features = max_features, token_pattern = r'\b\w+\b', min_df = 1, max_df = 1.0)
    x = CV.fit_transform(x).toarray()

    vocabulary = CV.vocabulary_

    with open('vocabulary_php.pickle', 'wb') as f:
        pickle.dump(vocabulary, f)

    transformer = TfidfTransformer(smooth_idf = False)
    x_tfidf = transformer.fit_transform(x)
    x = x_tfidf.toarray()

    print(len(filename_index))
    print(x.shape)
    x = pd.DataFrame(x,index = filename_index)

    return x, y


def do_metrics(y_test, y_pred):
    print(y_test)
    print ("metrics.accuracy_score:")
    print (metrics.accuracy_score(y_test, y_pred))
    print ("metrics.confusion_matrix:")
    print (metrics.confusion_matrix(y_test, y_pred))
    print ("metrics.precision_score:")
    print (metrics.precision_score(y_test, y_pred))
    print ("metrics.recall_score:")
    print (metrics.recall_score(y_test, y_pred))


def do_GNB(x, y):
    x_train, x_test, y_train, y_test = train_test_split(x, y, test_size=0.2, random_state=0)
    clf = GaussianNB()
    clf.fit(x_train, y_train)
    print(len(x_test.index.values))   # 返回行的标签，即用于测试的文件名列表
    test_filename_list = x_test.index.values
    y_pred = clf.predict(x_test)
    for i in range(len(x_test)):
        if y_pred[i] == y_test[i]:
            print("分类正确，"+ "文件"+ test_filename_list[i] + "是" +nor_web[y_test[i]])
        else:
            print("分类错误，" + "文件" + test_filename_list[i] + "是" + nor_web[y_test[i]])
    with open('GNB_php.pickle', 'wb') as f:
        pickle.dump(clf, f)

    do_metrics(y_test, y_pred)


if __name__ == '__main__':

    x, y = get_feature_by_wordbag_tfidf()
    print ("Load %d white files %d black files" % (white_count, black_count))

    do_GNB(x, y)
