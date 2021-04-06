import shutil
import os

import jieba
import jieba.posseg as pseg
import os
import sys
from sklearn import feature_extraction
from sklearn.feature_extraction.text import TfidfTransformer
from sklearn.feature_extraction.text import CountVectorizer

def test1():
    corpus = ["我 来到 北京 清华大学",  # 第一类文本切词后的结果，词之间以空格隔开
              "他 来到 了 网易 杭研 大厦",  # 第二类文本的切词结果
              "小明 硕士 毕业 与 中国 科学院",  # 第三类文本的切词结果
              "我 爱 北京 天安门"]  # 第四类文本的切词结果
    vectorizer = CountVectorizer()  # 该类会将文本中的词语转换为词频矩阵，矩阵元素a[i][j] 表示j词在i类文本下的词频
    transformer = TfidfTransformer()  # 该类会统计每个词语的tf-idf权值
    tfidf = transformer.fit_transform(
        vectorizer.fit_transform(corpus))  # 第一个fit_transform是计算tf-idf，第二个fit_transform是将文本转为词频矩阵
    word = vectorizer.get_feature_names()  # 获取词袋模型中的所有词语
    weight = tfidf.toarray()  # 将tf-idf矩阵抽取出来，元素a[i][j]表示j词在i类文本中的tf-idf权重
    for i in range(len(weight)):  # 打印每类文本的tf-idf词语权重，第一个for遍历所有文本，第二个for便利某一类文本下的词语权重
        print(corpus[i])
        # u"-------这里输出第", i, u"类文本的词语tf-idf权重------"
        for j in range(len(word)):
            print(word[j], weight[i][j])

def movjsp():
    cwd = os.getcwd()
    print(cwd)
    g = os.walk(cwd)
    num = 0
    for path, d, filelist in g:
        for filename in filelist:
            # endswith()方法的返回值为bool型，用于判断字符串是否以给定字符结尾
            if filename.endswith('.jsp'):
                fulpath = os.path.join(path, filename)
                # print("Load %s" % fulpath)
                #t = load_str(fulpath)
                shutil.copy(fulpath, '../jsp/')
                print(fulpath + " move successfully")
                num = num + 1
    print("The number of jsp is %d" % num)

def test():
    wod = ["qqqqq","wwww","eeeeeeee"]
    print(wod[:1])

if __name__ == '__main__':
    #movjsp()
    test1()