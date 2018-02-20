# A quick script to be able to change the 
# mongo server host by command line.
# (can be updated to change any value...)

import json
import sys

with open('conf.json', 'r+') as f:
    data = json.load(f)
    last_value =  data['mongodb']['host']
    if len(sys.argv)==1:
      print('No replacement value given.')
      sys.exit()
    new_value = sys.argv[1]
    data['mongodb']['host'] = new_value # update value
    f.seek(0)        # reset file position to the beginning.
    json.dump(data, f, indent=2)
    f.truncate()     # remove remaining part
    print('Replaced {} with {} on mongodb.host'.format(last_value,new_value))