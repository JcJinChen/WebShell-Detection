import os
import pandas
import random
import json

import warnings
warnings.filterwarnings('ignore')

from sklearn.feature_extraction.text import CountVectorizer
from sklearn.model_selection import train_test_split
from sklearn.naive_bayes import GaussianNB
from sklearn import metrics
from sklearn.feature_extraction.text import TfidfTransformer
"""
sklearn中文本特征提取类feature_extraction.text,函数CountVectorizer和函数TfidfTransformer得到
"""

import pickle
import chardet


# 设置最大维度特征，语料库大小
max_features = 50000
nor_web = {0: "normalfile", 1:"webshell"}

webshell_dir = "Data/WebShell/php/"
normal_dir = "Data/normal/php/"
"""
webshell_dir中存放webshell训练样本
normal_dir中存放正常php训练样本
"""

white_count = 0
black_count = 0


def check_style(filepath):
    '''
    得到文件的编码方式
    '''
    # open()打开一个文件，创建一个file对象；
    # open(name[, mode[, buffering]]) name:包含访问文件名称的字符串值；
    # mode = 'rb' 以二进制格式打开一个文件用于只读
    with open(filepath, mode='rb') as f:
        data = f.read()
        # detect返回一个字典：利用自动监测得到字符编码以及0到1之间的置信度
        style = chardet.detect(data)['encoding']
        return style


def load_str(filepath):
    """
    得到文件内容构成的拼接字符串；
    把php文件作为一个完整的字符串处理
    """
    t = ""
    # 处理try,except异常
    try:
        style = check_style(filepath)
        with open(filepath, encoding=style) as f:
            for line in f:
                # strip()去除字符串中首尾指定字符 \r return 回车 \n newline 换行
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
    """
    1，随机得到的文件地址列表,；2，根据地址得到文件字符串；
    最后，一个以各文件内容为元素的列表；
    """
    # files_list训练文件列表，res_files_list用于check文件列表
    # files_path 训练文件路径列表，res_files_path用于check文件路径列表
    # 两个文件列表作为输出 ——
    # 4/1修改 将文件名和文本列表拼成字典输出
    files_list = []
    res_files_list = []
    files_path, res_files_path = rand_filepath(dir, sample_num)
    for filename in files_path:
        print ("Load %s" % filename)
        t = load_str(filename)
        files_list.append(t)
    for filename in res_files_path:
        print ("Load %s" % filename)
        t = load_str(filename)
        res_files_list.append(t)
    dic_files = dict(zip(files_path, files_list))
    dic_res_files = dict(zip(res_files_path, res_files_list))
    # return files_list, res_files_list
    return dic_files, dic_res_files

def Merge(dict1, dict2):
    res = {**dict1, **dict2}
    return res

def get_feature_by_wordbag_tfidf():
    """
    得到词袋模型
    :return: x,y 返回值分别为tf-idf和对应文件的标记
    """
    # global为变量申明为全局变量的关键字
    global max_features
    global white_count
    global black_count

    print ("max_features = %d" % max_features)

    webshell_num = 1338
    rate = 3
    # 将webshell、正常php样本分别标记为1和0
    webshell_files_dic, check_webshell_files_dic = load_files(webshell_dir, webshell_num)
    print(check_webshell_files_dic)
    with open('res_webshell_php.pickle', 'wb') as f:
        pickle.dump(check_webshell_files_dic , f)
    #
    # words = webshell_files_list[0].split()
    # print(words)
    # print("\nthe number of webshell_files_list[0] is" +str(len(words)) )

    #webshell_files_list = webshell_files_list[:1000]
    y1 = [1] * len(webshell_files_dic)
    # check_y1 = [0] * len(check_webshell_files_list)
    black_count = len(webshell_files_dic)

    normal_sample_num = webshell_num * rate
    normal_files_dic, check_normal_files_dic = load_files(normal_dir, normal_sample_num)
    # print("the length check_normal_files_list is %d "%len(check_normal_files_dic))
    # normal_files_list = normal_files_list[:1000]
    # with open('res_normal_php.pickle', 'wb') as f:
    #     pickle.dump(check_normal_files_dic , f)
    # y2 = [0] * len(normal_files_dic)
    # # check_y2 = [0] * len(check_normal_files_list)
    # white_count = len(normal_files_dic)
    #
    # x = list(webshell_files_dic.values()) + list(normal_files_dic.values())
    # y = y1 + y2

    # check_x = check_webshell_files_list + check_normal_files_list
    # check_y = check_y1 + check_y2

    # CountVectorizer将文本中的词语转换为词频矩阵，再通过fit_transform函数计算各个词语出现的次数,
    # ngram_range(min, max)表示在min,max之间的所有的n 元gram
    # vocabulary：表示术语到特征索引的映射，字典dic;可以用len()求其长度
    # CV = CountVectorizer(ngram_range = (2, 2), decode_error = 'ignore', max_features = max_features, token_pattern = r'\b\w+\b', min_df = 1, max_df = 1.0)
    # x = CV.fit_transform(x).toarray()
    # # CountVectorizer.fit_transform()得到的为稀疏矩阵
    #
    #
    # vocabulary = CV.vocabulary_
    #  print(vocabulary)
    # open()在写文件时，如果写入的文件不存在，函数open()自动创建
    # with open('vocabulary_php.pickle', 'wb') as f:
    #     # pickle(obj, file, protocol) 将obj对象序列化存入已经打开的file中
    #     # pickle.load(file) 将file对象序列化读出
    #     pickle.dump(vocabulary, f)
    #
    # transformer = TfidfTransformer(smooth_idf = False)
    # x_tfidf = transformer.fit_transform(x)
    # x = x_tfidf.toarray()

    return x, y


def do_metrics(y_test, y_pred):
    print ("metrics.accuracy_score:")
    print (metrics.accuracy_score(y_test, y_pred))  #分类准确率指分类正确的百分比，返回正确的样本数
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
    y_pred = clf.predict(x_test)

    with open('GNB_php.pickle', 'wb') as f:
        pickle.dump(clf, f)

    do_metrics(y_test, y_pred)

def check():
    all_php = 0
    webshell = 0

    pickle_in1 = open('vocabulary_php.pickle', 'rb')
    vocabulary1 = pickle.load(pickle_in1)

    CV1 = CountVectorizer(ngram_range=(2, 2), decode_error='ignore', max_features=max_features,
                          token_pattern=r'\b\w+\b', min_df=1, max_df=1.0, vocabulary=vocabulary1)

    transformer = TfidfTransformer(smooth_idf=False)

    pickle_in_1 = open('GNB_php.pickle', 'rb')
    clf1 = pickle.load(pickle_in_1)

    # res_normal_php.pickle保存剩余白样本文件
    pickle_normal = open('res_normal_php.pickle', 'rb')
    res_normal_php = pickle.load(pickle_normal)
    print("the type of res_normal_php is ")
    print(type(res_normal_php))
    for file_path, normal_file in res_normal_php.items():
        all_php += 1
        t_list = []
        t_list.append(normal_file)
        x = CV1.fit_transform(t_list).toarray()
        x = transformer.fit_transform(x).toarray()
        y_pred = clf1.predict(x)
        if y_pred[0] == 1:
            webshell += 1
        print(json.dumps({'filename': file_path, 'result': int(y_pred[0])}, sort_keys=True, indent=4,
                         separators=(',', ': ')))
    print("the number of php %d, the number of webshell%d"%(all_php, webshell))


if __name__ == '__main__':

    x, y= get_feature_by_wordbag_tfidf()
    print ("Load %d white files %d black files" % (white_count, black_count))

    do_GNB(x, y)

 #   check()