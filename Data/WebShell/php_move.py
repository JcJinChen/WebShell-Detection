import shutil
import os

def movphp():
    cwd = os.getcwd()
    g = os.walk(cwd)
    num = 0
    for path, d, filelist in g:
        for filename in filelist:
            # endswith()方法的返回值为bool型，用于判断字符串是否以给定字符结尾
            if filename.endswith('.php'):
                fulpath = os.path.join(path, filename)
                # print("Load %s" % fulpath)
                #t = load_str(fulpath)
                shutil.copy(fulpath, './allphp/')
                print(fulpath + " move successfully")
                num = num + 1
    print("The number of php is %d" % num)

def test():
    wod = ["qqqqq","wwww","eeeeeeee"]
    print(wod[:1])

if __name__ == '__main__':
    #movphp()
    test()