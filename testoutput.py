import pickle

pickle_normal = open('res_normal_php.pickle', 'rb')
res_normal_php = pickle.load(pickle_normal)
print(len(res_normal_php))