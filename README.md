# The goal

Create a multi-container Docker application with Docker Compose that would have the following services:

- 1 Redis container
- 2 Apache containers
    - serve one index.php file that
   	-- outputs a json containing the current date and the IP of the user
   	-- sets a session variable called `count` that is incremented on each visit
    - save all session information in the Redis container
    - defer all interpreting of PHP code to the PHP-FPM container
    - have error logging and access logging enabled
    - logs need to be sent to the Logstash container
- 1 PHP-FPM container
    - PHP7.1
    - used for interpreting PHP code from the Apache containers
- 1 Load Balancer for the Apache containers
- 1 Jenkins container
    - has a job that runs once per day and deletes indexes older than 30 days from Elasticsearch
- 1 Elasticsearch container
- 1 Kibana container
    - allows viewing the data from the Elasticsearch container
- 1 Logstash container
    - ingests access and error logs from the Apache containers and saves to Elasticsearch

# Getting started

Clone this repository
```
git clone https://github.com/enriconicoletti/tr-test
```
run (or restart) the docker cluster with
```
sudo sysctl -w vm.max_map_count=262144 && docker-compose down && docker-compose build && docker-compose up --scale web=2
```
The changes to vm.max_map_count are needed for this reason (not permanent after reboot):
https://www.elastic.co/guide/en/elasticsearch/reference/current/vm-max-map-count.html

# Usage

Here are the port mappings to access (from localhost) the various services running in the containers:
- *loadbalancer: port 8080* (eg: http://localhost:8080). To see the index.php, debug.php and devops.jpg
- *kibana: port 5601* (eg: http://localhost:5601). The Kibana UI to see a chart with the logging events count displayed on a timeline
- *elasticsearch json api: port 9200* (eg: http://localhost:9200/_search?pretty). The json api of elasticsearch
- *elasticsearch transport: port 9300*. Elastic transport is listening on elk:9300 (elk is the hostname assigned in the docker compose network. It is reachable by the other containers on the same network but not from the host)
- *logstash: port 5044*. Where logstash is listening for logging events to store
- *rebrow: port 5001*. A Redis web UI to explore the stored key/values

_TIP: If needed you can log to a container shell using_
```
docker exec -t -i container_name /bin/bash
```

# Implementation Walkthrough

## Step 1: Webserver behind a load balancer and a simple index.php

The goal for the first step is to have a running apache serving an helloworld PHP page. 

- Created the docker compose folder structure; a `web` subfolder with the first `Dockerfile`; and an example `index.php` just echoing an Hello world string. I set the port mapping to access the webserver from port 8080 of the host. The content of the apache `www` folder is copied in the containter at build time.

- Added a simple round-robin load balancer using the dockercloud `HAProxy` image. I linked the load balancer to the webserver container and moved the _80:8080_ port mapping to the load balancer, as the host doesn't need anymore to connect to the webserver directly. Now is possible to specify the `--scale web=2`  command line parameter to spawn multiple webservers instances behind the loadbalancer.

- Adjusted the PHP file to output the IP, the timestamp and a visits counter (not yet shared). The content-type is changed to interpret the output as json.

### Test if working
Start the cluster as mentioned before and point the browser to http://localhost:8080. You should see a json with a visits counter that is incremented every second page refresh, indicating that we are hitting different webservers behind the load balancer.

_NOTE: Before moving to the next step we need to remove from the `Dockerfile` all PHP packeges installed in this container. PHP scripts interpretation will be moved to a separate container, so we don't need them anymore._

## Step 2: Delegate PHP interpretation to PHP-FPM and store session counter in Redis

- Found an official PHP-FPM image online (https://github.com/docker-library/php) to be used as a base.

- Changed apache config to forward php interpretation to PHP-FPM 

```
DocumentRoot /var/www/html
ProxyPassMatch ^/(.*\.php(/.*)?)$ fcgi://fastcgi:9000/var/www/html/$1
```

- The `/var/www/html` folder is now mounted as a volume in all `web` and `fastcgi` containers, so it's shared. PHP_FPM will take care of the PHP scripts (due to the ProxyPassMatch pattern) while static resources will still be served directly by Apache.

- Installed and enabled the `proxy_fcgi` apache module to use the _ProxyPassMatch_ directive. Additionally I Installed the `php-redis` extension (using pecl) to enable PHP to use Redis as session storage system.

- Configured PHP to use Redis: changed this values in `php.ini` file and configured the Dockerfile to copy `php.ini` at containter build time
```
session.save_handler = redis
session.save_path = "tcp://redis:6379"
extension=redis.so
```

- Found a Redis image online (https://hub.docker.com/_/redis/) and set it up in `docker-compose.yml`


### Test if working
Go to http://localhost:8080. You should get a JSON. Refresh the page multiple times; the counter should increase at each refresh. The PHP container is now a single one, so this is not proving that the counter is correctly stored in Redis.

To check the value stored in Redis open http://localhost:5001 and use `redis` as hostname. Click on the _Keys_ menu, and check the value stored in the `PHPREDIS_SESSION` key. It should be something like `count|i:12;`

To check if the PHP interpretation is working correctly go to http://localhost:8080/debug.php.
There is a list of headers used to infer the IP address. In the _phpinfo()_ part check if `Server API` is `FPM/FastCGI`. Also note that the `System` value now remains the same after refreshing the page, giving somehow an additional proof that the PHP_FPM container is interpreting the PHP script.

We want to delegate only the PHP files, the static resources should still be managed by Apache for performanace reasons. To prove that go to http://localhost:8080/devops.jpg; this is a static resource served by Apache. Now kill the fastcgi container (eg: ```docker kill tr-test_fastcgi_1```), the `debug.php` and `index.php` pages should not be reachable anymore, while should still be possible to see the image at http://localhost:8080/devops.jpg

## Step 3: Decentralized logging

We already have apache properly configured to save logs
```
  	ErrorLog ${APACHE_LOG_DIR}/error.log
  	CustomLog ${APACHE_LOG_DIR}/access.log combined
```

- Installed filebeat (`/usr/share/filebeat`) in the web container in order to forward this logs to logstash. Also configured filebeat service to start with the container. They are started this way, even if not optimal.

```
# Start filebeat and apache
# Not best practice, there should be a separate filebeat container with the logs mounted as volumes but it's overkill for a demo
CMD /etc/init.d/filebeat start && \
    /usr/sbin/apache2ctl -D FOREGROUND
```

- Created a `filebeat.yml` config file that is copied into the `ẁeb` container at build time. In this config file I set the input files path and linked the output to the logstash endpoint that is reachable in the composer network through the "elk" hostname (see snippet below). 

```
filebeat:
  # List of prospectors to fetch data.
  prospectors:
      paths:
        - /var/log/apache2/access.log
        - /var/log/apache2/error.log

output:
  logstash:
    enabled: true
    hosts:
      - elk:5044
    timeout: 15
```

- Found a fast to setup distribution of the elk stack here https://elk-docker.readthedocs.io/. At the beginning I had some issues with the log forwarding, the issue was the certificate configuration on the logstash side. I disabled the logstash SSL configuration part to set it up faster (not ok for production). A self signed certificate is provided with the image, but for production use would be better to have a proper certificate, so for this demo I skipped the SSL part entirely. In the documentation linked above there are hints on how to improve the security for production usage.

```
input {
  beats {
    port => 5044
    ssl => false
    # ssl_certificate => "/etc/pki/tls/certs/logstash-beats.crt"
    # ssl_key => "/etc/pki/tls/private/logstash-beats.key"
  }
}
```
In order to debug this issue and to see if the problem is in the communication _web->elk_ or in the elk container itself, you can create a fake log entry this way: 
```
# Connect to elk container and run
/opt/logstash/bin/logstash --path.data /tmp/logstash/data \
    -e 'input { stdin { } } output { elasticsearch { hosts => ["localhost"] } }'
```
I was able to see my manually generated message correctly stored in Elastic (http://localhost:9200/_search?pretty), so the problem was on the _web->elk_ communication part. The communication problem was anyway visible in the filebeat log (/var/log/filebeat) but the logging level needs to be tweaked at the bottom of filebeat.yml to get usable logging.

### Test if working

Go to http://localhost:5601 to access the Kibana UI. Click _"Management"_ and _"Index pattern"_ to define which indexes should be used. As a value use `filebeat-*` and for timestamp use the `@timestamp` field.

Refresh multiple times the page at http://localhost:8080 in order to generate some apache access.log event. 

In Kibana UI click on _"Discover"_ menu and then set automatic refresh of the UI 5 seconds for convenience. You should be able to see a chart with the count of access+error events coming from the web container.


## Step 4: Elastic indexes cleanup
 
To cleanup old indexes I use a Jenkins job that starts around midnight and makes CURL call to elastic search API to delete the old indexes. Setting up the image is straightforward.

- At first I used the `jenkins/jenkins:latest` image. The issue here is that jenkins starts with no user and with a setup wizard. Our goal instead is to have an preconfigured instance with some plugins and a job.
At first I planned to run jenkins, set it up manually from the UI and then copy the /var/jenkins_home folder which contains all configuration (and plugins). This would have worked, but the folder was quite big (~100MB) also for a basic installation with few plugins, so I decided to go for a cleaner alternative.

- I found a different image that I used as a base to develop my own Dockerfile (https://github.com/foxylion/docker-jenkins). This provided useful hints on how to set a default user and how to configure jenkins to skip the setup wizard. I copy pasted pieces of it to create my docker image based on the `jenkins/jenkins` one. 

- After starting the container I configured (through the Jenkins UI) a new job that runs at "@midnight" and runs some shell commands, reported here:

```
#!/bin/bash

OLDERTHAN=30 # Delete indexes older than N days

# Zero padded days using %d instead of %e
DAYSAGO=`date --date="$OLDERTHAN days ago" +%Y%m%d`

echo "Searching and delete indexes created before date (yyyymmdd): $DAYSAGO"
ALLLINES=`/usr/bin/curl --silent --show-error -XGET http://elk:9200/_cat/indices?v | egrep filebeat`
echo
echo "ALL AVAILABLE INDEXES"
echo "$ALLLINES"
echo
echo "DELETE OLD INDEXES"
echo "$ALLLINES" | while read LINE
do
  FORMATEDLINE=`echo $LINE | awk '{ print $3 }' | awk -F'-' '{ print $2 }' | sed 's/\.//g' `
  if [ "$FORMATEDLINE" -lt "$DAYSAGO" ]
  then
    TODELETE=`echo $LINE | awk '{ print $3 }'`
    printf "Deleting $TODELETE ...  "
    /usr/bin/curl --silent --show-error -XDELETE http://elk:9200/$TODELETE
    sleep 1
    fi
done
``` 

Here is an exmaple run output

```
Started by user admin
Building in workspace /var/jenkins_home/jobs/elastic-index-cleanup/workspace
[workspace] $ /bin/bash /tmp/jenkins5732771649794239224.sh
Search and delete indexes created before date (yyyymmdd): 20180507

ALL AVAILABLE INDEXES
yellow open   filebeat-2018.05.06 VBH6y51MQzekw59LA_kusw   5   1          8            0     73.3kb         73.3kb

INDEXES TO BE DELETED
Deleting filebeat-2018.05.06 ...  {"acknowledged":true}Finished: SUCCESS
```

This script was created using some suggestions found online (https://stackoverflow.com/questions/33430055/removing-old-indices-in-elasticsearch). Using Curator (https://github.com/elastic/curator) would probably be a better option for production environment as this script is not optimized. For example is not checking if the index is deleted nor has proper error management.

- After I manually configured and tested the job, I then copied the "jobs" folder form the running container (```docker cp <container>:<sourcefile><dest>```) and changed the Dockerfile to copy this folder at container build time. Note that the jobs folder should be copied from Docker file specifying "jenkins" as owner:group, otherwise jenkins will not load properly because it cannot write in it (will be owned by root if not differently specified).

### Test if working
Go to http://localhost:9090, access with username `admin` and password `admin` and run the `elastic-index-cleanup` job. Check if the console output is similar to the one reported above. Out of the box it deletes only indexes older than 30 days. If you want to delete the index that was just created at cluster startup, change to '-1' the value of 
```
OLDERTHAN=30 # Delete indexes older than N days
```
and re-run the job.


