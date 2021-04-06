import shutil
import os

def movasp():
    cwd = os.getcwd()
    g = os.walk(cwd)
    print(cwd)
    num = 0
    for path, d, filelist in g:
        for filename in filelist:
            # endswith()方法的返回值为bool型，用于判断字符串是否以给定字符结尾
            if filename.endswith('.asp'):
                fulpath = os.path.join(path, filename)
                # print("Load %s" % fulpath)
                #t = load_str(fulpath)
                shutil.copy(fulpath, '../asp/')
                print(fulpath + " move successfully")
                num = num + 1
    print("The number of asp is %d" % num)



if __name__ == '__main__':
    movasp()