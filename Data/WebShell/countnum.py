import os

def sum(dir):
    all_php = 0
    all_jsp = 0
    all_asp = 0
    g = os.walk(dir)
    # print(g)
    for path, d, filelist in g:
        for filename in filelist:
            # print(filename)
            # fulpath = os.path.join(path, filename)
            # all += 1
            if filename.endswith('.jsp') : #or filename.endswith('.txt'):
                all_jsp += 1
                # t = load_str(fulpath)
                # t_list = []
                # t_list.append(t)
                # x = CV1.transform(t_list).toarray()
                # x = transformer.fit_transform(x).toarray()
                # y_pred = clf1.predict(x)
            elif filename.endswith('.php') :
                all_asp += 1

    return all_asp

if __name__ == '__main__':
    # Dir1 = r'F:\容器安全\WebShell-AIHunter\Data\WebShell\jsp'
    # print("the number of jsp webshell is %d"%sum(Dir1))
    # Dir2 = r'F:\容器安全\WebShell-AIHunter\Data\normal\jsp'
    # print("the number of jsp normal is %d"%sum(Dir2))
    # Dir3 = r'F:\容器安全\WebShell-AIHunter\Data\WebShell\php'
    # print("the number of asp webshell is %d" % sum(Dir3))
    # print(1)
    list1 = ['physics', 'chemistry', 1997, 2000]
    list2 = [1, 2, 3, 4, 5]
    dic = dict(zip(list1, list2))
    print(list(dic.values()))