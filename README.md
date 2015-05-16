# twitter-cluster
PHP Code to cluster Twitter followers into groups.

Load the users and who they follow into the database and then execute the php script.

The resulting clusters and thier members are stored in the Cluster and Cluster_Member tables.

There are some configuration options at the top of the php script, these allow you to change the strength of the clustering, you should experiment with a few different values to see what works best for your data.

