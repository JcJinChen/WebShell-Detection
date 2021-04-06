import os

from sklearn.feature_extraction.text import CountVectorizer
from sklearn.feature_extraction.text import TfidfTransformer

import pickle
import chardet
import json

# json
# chardet模块，即Universal Encoding Detector，用于字符检测的模块，识别未知格式的字节属于什么编码格式。
# 主模块中只有一个函数detect()，参数要求为bytes。bytes类型可以通过读取网页内容、open函数的rb模式、
# 带b前缀的字符串、encode函数等途径获得。

import warnings
warnings.filterwarnings('ignore')

max_features = 50000

check_dir = "Data/check/"


def check_style(filepath):
    with open(filepath, mode='rb') as f:
        data = f.read()
        # detect()函数返回一个字典，两个键值对key-value：{'encoding':编码方式,'confidence':0~1}
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


def check_webshell(dir):
    all = 0
    all_php = 0
    all_asp = 0
    all_jsp = 0
    other = 0
    webshell = 0

    pickle_in1 = open('vocabulary_php.pickle', 'rb')
    vocabulary1 = pickle.load(pickle_in1)
    '''
    pickle_in2 = open('vocabulary_asp.pickle', 'rb')
    vocabulary2 = pickle.load(pickle_in2)

    pickle_in3 = open('vocabulary_jsp.pickle', 'rb')
    vocabulary3 = pickle.load(pickle_in3)
    '''
    CV1 = CountVectorizer(ngram_range = (2, 2), decode_error = 'ignore', max_features = max_features, token_pattern = r'\b\w+\b', min_df = 1, max_df = 1.0, vocabulary = vocabulary1)
    '''
    CV2 = CountVectorizer(ngram_range = (2, 2), decode_error = 'ignore', max_features = max_features, token_pattern = r'\b\w+\b', min_df = 1, max_df = 1.0, vocabulary = vocabulary2)
    CV3 = CountVectorizer(ngram_range = (2, 2), decode_error = 'ignore', max_features = max_features, token_pattern = r'\b\w+\b', min_df = 1, max_df = 1.0, vocabulary = vocabulary3)
    '''
    transformer = TfidfTransformer(smooth_idf = False)

    pickle_in_1 = open('GNB_php.pickle', 'rb')
    clf1 = pickle.load(pickle_in_1)
    '''
    pickle_in_2 = open('GNB_asp.pickle', 'rb')
    clf2 = pickle.load(pickle_in_2)

    pickle_in_3 = open('GNB_jsp.pickle', 'rb')
    clf3 = pickle.load(pickle_in_3)
    '''
    g = os.walk(dir)
    for path, d, filelist in g:
        for filename in filelist:
            fulpath = os.path.join(path, filename)
            all += 1
            if filename.endswith('.php') or filename.endswith('.txt'):
                all_php += 1
                t = load_str(fulpath)
                t_list = []
                t_list.append(t)
                x = CV1.transform(t_list).toarray()
                x = transformer.fit_transform(x).toarray()
                y_pred = clf1.predict(x)
            elif filename.endswith('.asp') or filename.endswith('.txt'):
                all_asp += 1
                t = load_str(fulpath)
                t_list = []
                t_list.append(t)
                x = CV2.transform(t_list).toarray()
                x = transformer.fit_transform(x).toarray()
                y_pred = clf2.predict(x)
            elif filename.endswith('.jsp') or filename.endswith('.txt'):
                all_jsp += 1
                t = load_str(fulpath)
                t_list = []
                t_list.append(t)
                x = CV3.transform(t_list).toarray()
                x = transformer.fit_transform(x).toarray()
                y_pred = clf3.predict(x)
            else:
                other += 1

            if y_pred[0] == 1:
                webshell += 1
            print (json.dumps({'filename': filename,'result': int(y_pred[0])}, sort_keys=True, indent=4, separators=(',', ': ')))

    print ("Scan %d files(%d php files, %d asp files, %d jsp files, %d other files),%d files is webshell" % (all, all_php, all_asp, all_jsp, other, webshell))

if __name__ == '__main__':

    check_webshell(check_dir)
