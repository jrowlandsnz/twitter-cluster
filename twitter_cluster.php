<?php
/**
 * This will cluster twitter users based on them following the same people
 * Users can be in more than one cluster
 * Resulting clusters will be inserted into the database for later querying
 */
error_reporting(E_ALL);
//ini_set('memory_limit','2048M');
set_time_limit(0);	
include_once('logger.class.php'); //simple logging class

//configuration section
$databaseServer = 'localhost';
$databaseUser = 'root';
$databasePass = 'root';
$databaseName = 'twitter_cluster';

//note user must have create table rights on database
$db = new mysqli($databaseServer, $databaseUser, $databasePass, $databaseName);
$logger = new Logger();
$logger->displayLineNumbers = false;
$logger->displayOnScreen = true;
$logger->startTimer();

//query must return 3 columns - user_id, name, follows_user_id
$sql = "SELECT DISTINCT u.user_id, u.screen_name as name, f.follows_user_id FROM `following` f 
INNER JOIN user u ON u.user_id = f.user_id 
INNER JOIN user u1 ON u1.user_id = f.follows_user_id";


//configuration of clustering parameters
$mergeClustersAtEnd = true; //merge clusters at end to form larger ones
$minimumRatioFollowers = 0.7; //when merging clusters what is the minimum ratio of common users before merging
$clusterSizeToUseRatio = 5; //minimum size of cluster to use above ratio, otherwise ratio is 1
$removeSmallerClusters = true; //whether or not to delete a small cluster once it is part of a larger cluster
$insertClustersIntoDB = true; //whether or not to insert clusters into database
$minimumCommonFollowersRatio = 0.8; //minimum common followers between two users in order to start a cluster from them
$printClustersAtEnd = true; //whether to display the clusters on the screen
$backupCurrentClusterTable = true; //whether to backup the current cluater and cluster member tables
$backupTableSuffix = "_".(int)$logger->getMicrotime(); //if backing up suffix to add to table name

//clustering code starts from here
$following = array();  //array where key is user id, value is an array of the users they follow
$followedBy = array();  //array of users as key with an array of users who follow them
$usernames = array(); //array of usernames with their userid as key, used to aid debugging

if ($db->connect_errno) {
    $logger->logError("Failed to connect to MySQL: (" . $db->connect_errno . ") " . $db->connect_error);
	exit;
}

$res = $db->query($sql);
if(!$res) {
	$logger->logError('Error '.$db->error);
}
else {
	$currentUser = 0;
	while($row = $res->fetch_assoc()) {
		$newUser = "".$row['user_id'];
		if(!array_key_exists($newUser, $following)) {
			$following[$newUser] = array();
			$currentUser = $newUser;
			$usernames[$newUser] = $row['name'];
		}
		
		$following[$newUser][] = "".$row['follows_user_id'];
	}
	
	//clean up following array to only have the users in the array in the following lists
	foreach($following as $user => $followingUsers) {
		//use for loop so we can remove the index
		for($i = 0; $i < sizeof($followingUsers); $i++) {
			if(array_key_exists($followingUsers[$i], $following) === FALSE) {
				unset($following[$user][$i]);
			}
		}		
	}
	
	//set up followed by array (inverse of following array)
	foreach($following as $user => $followingUsers) {
		foreach($followingUsers as $followedUser) {
			if(array_key_exists($followedUser, $followedBy) === FALSE) {
				$followedBy[$followedUser] = array();
			}
			$followedBy[$followedUser][] = $user;
		}	
	}
	
	try {
		$clustersToRemove = array(); //used when merging clusters if that option is enabled
		$clusters = array();			
		$userIds = array_keys($followedBy);
		$logger->logDebug($userIds);
		
		//loop through each user and find the user closest to them as starting point of cluster
		//closest user defined as highest number of mutual followers
		
		foreach($userIds as $user) {	
			$currentUserId = $user; 
			$currentCluster = array();
			
			$currentMaxScore = 0;
			$currentClosestUser = 0;
			
			foreach($userIds as $potentialClosestUserId) {	
				if($potentialClosestUserId != $currentUserId) {
					$numberMutualFollowing = sizeof(array_intersect($followedBy[$currentUserId], $followedBy[$potentialClosestUserId]));
					if($numberMutualFollowing > $currentMaxScore) {
						$logger->logDebug("New Max Score $numberMutualFollowing");
						$currentMaxScore = $numberMutualFollowing;
						$currentClosestUser = $potentialClosestUserId;
					}
				}
			}
			
			$numberFollowing = (int)sizeof($followedBy[$currentUserId]);
			if($numberFollowing == 0) {
				$percentageCommonUsers = 0;
			}
			else {
				$percentageCommonUsers = (int)$currentMaxScore / $numberFollowing	;
			}
			
			if($percentageCommonUsers < $minimumCommonFollowersRatio) {
				//not many common followers so create a cluster of 1
				$currentCluster[] = $currentUserId;
				$logger->logDebug("Creating single cluster for $currentUserId");
			}
			else {
				//start a cluster from these 2 users
				$addedToCluster = 1;
				$currentCluster[] = $currentUserId;
				$currentCluster[] = $currentClosestUser;
				$logger->logDebug("Starting cluster from $currentUserId");
				while($addedToCluster > 0) {
					$addedToCluster = 0;
					$logger->logDebug($currentCluster);
					
					$clusterFollowing = $followedBy[$currentUserId];
					foreach($currentCluster as $clusterMember) {
						$clusterFollowing = array_intersect($clusterFollowing, $followedBy[$clusterMember]);
					}
					$logger->logDebug($clusterFollowing);
					
					//of the common followers, which one follows the most in this cluster?
					$currentMaxScore = 0;
					$currentClosestUser = 0;
					
					foreach($clusterFollowing as $potentialClosestUserId) {
						//make sure this user isn't in current array
						if(array_search($potentialClosestUserId, $currentCluster) === FALSE) {
							$numberMutualFollowing = sizeof(array_intersect($clusterFollowing, $followedBy[$potentialClosestUserId]));
							if($numberMutualFollowing > $currentMaxScore) {
								$logger->logDebug("New Max Score $numberMutualFollowing");
								$currentMaxScore = $numberMutualFollowing;
								$currentClosestUser = $potentialClosestUserId;
							}
						}
					}
					
					//see if there are enough common followers
					$minNumberCommonFollowingNeeded = sizeof($currentCluster);
					if(sizeof($currentCluster) > $clusterSizeToUseRatio) {
						$minNumberCommonFollowingNeeded = sizeof($currentCluster) * $minimumRatioFollowers;
					}
					
					if($currentMaxScore > $minNumberCommonFollowingNeeded) {
						$addedToCluster++;
						$currentCluster[] = $currentClosestUser;
					}
					
				}
				
			}
			
			//add to clusters array
			sort($currentCluster);
			$arrayKey = "";
			foreach($currentCluster as $newClusterKeyMember) {
				$arrayKey .= $newClusterKeyMember."-";
			}
			
			if(!array_key_exists($arrayKey, $clusters)) {
				$clusters[$arrayKey] = $currentCluster;
			}	
		}
		
		//}
		
		/**
		foreach($clusters as $key => $members) {
			$logger->logDebug($key);	
			$memberString = "";
			foreach($members as $member) {
				$memberString .= $usernames[$member]." ";
			}
			$logger->logDebug($memberString);	
		}
		// */
		
		//merge clusters
		if($mergeClustersAtEnd) {
			//merge clusters based on having similar users
			$logger->logDebug("Pre merge clusters size: ".sizeof($clusters));
			
			
			//modify this to merge with closest cluster
			$clustersMerged = 1;
			while($clustersMerged > 0) {
				$clustersMerged = 0;
				foreach($clusters as $clusterKey => $clusterMembers) {
					
					
					//find best cluster to merge with (if any)	
					$maxCommonUserScore = 0;
					$maxScoreClusterId = "";
					$currentClusterSize = sizeof($clusterMembers);
						
					foreach ($clusters as $clusterKeyToMerge => $clusterMembersToMerge) {
						if($clusterKey != $clusterKeyToMerge) {
							//only merge smaller clusters into larger ones	
							if(sizeof($clusterMembersToMerge) >= $currentClusterSize) {
								$commonFollowersCount = sizeof(array_intersect($clusterMembers, $clusterMembersToMerge));	
								if($commonFollowersCount > $maxCommonUserScore) {
									$maxCommonUserScore = $commonFollowersCount;	
									$maxScoreClusterId = $clusterKeyToMerge;
								}
							}	
						}
					}
													
					if(($maxCommonUserScore/$currentClusterSize) >= $minimumRatioFollowers)	 {
						//merge these two clusters

						$currentCluster = array();
						foreach($clusterMembers as $c) {
							$currentCluster[] = $c;
						}
						
						foreach ($clusters[$maxScoreClusterId] as $c) {
							if(array_search($c, $currentCluster) === FALSE) {
								$currentCluster[] = $c;
							}
						}
						
						sort($currentCluster);
						$arrayKey = "";
						foreach($currentCluster as $newClusterKeyMember) {
							$arrayKey .= $newClusterKeyMember."-";
						}
						
						if(!array_key_exists($arrayKey, $clusters)) {
							$clusters[$arrayKey] = $currentCluster;
						}	
						
						//remove the 2 source clusters from the array
						unset($clusters[$clusterKey]);
						unset($clusters[$maxScoreClusterId]);
						$clustersMerged++;
						reset($clusters);
						break;  //need to restart to loops again due to array in foreach now being out of date
					}			
				}
			}
			
			$logger->logDebug("Post merge clusters size: ".sizeof($clusters));
		}
					
		if($insertClustersIntoDB) {
			$logger->logDebug("Inserting into DB");
			
			if($backupCurrentClusterTable) {
				$sql = "RENAME TABLE cluster TO cluster".$backupTableSuffix;
				$logger->logDebug($sql);
				if(!$db->query($sql)) {
					$logger->logError("Error in $sql \n ".$db->error);
					exit;
				}
				
				$sql = "RENAME TABLE cluster_member TO cluster_member".$backupTableSuffix;
				$logger->logDebug($sql);
				if(!$db->query($sql)) {
					$logger->logError("Error in $sql \n ".$db->error);
					exit;
				}
				
				$sql = "CREATE TABLE cluster LIKE cluster".$backupTableSuffix;
				$logger->logDebug($sql);
				if(!$db->query($sql)) {
					$logger->logError("Error in $sql \n ".$db->error);
					exit;
				}
				
				$sql = "CREATE TABLE cluster_member LIKE cluster_member".$backupTableSuffix;
				$logger->logDebug($sql);
				if(!$db->query($sql)) {
					$logger->logError("Error in $sql \n ".$db->error);
					exit;
				}
			}
		
			$clusterSizeCount = array();
			foreach($clusters as $clusterKey => $clusterMembers) {
				//$logger->logDebug($clusterKey);	
				$key = "".sizeof($clusterMembers);
				if(array_key_exists($key, $clusterSizeCount)) {
					$clusterSizeCount[$key] = $clusterSizeCount[$key] + 1;
				}
				else {
					$clusterSizeCount[$key] = 1;
				}
			}
			
			foreach($clusters as $clusterKey => $clusterMembers) {
				$clusterSize = sizeof($clusterMembers);
				$sql = "INSERT INTO cluster SET cluster_size = $clusterSize, cluster_key = '$clusterKey'";
				//$logger->logDebug($sql);
				$res = $db->query($sql);
				if(!$res) {
					$logger->logError("Error in $sql \n ".$db->error);
					exit;
				}
				
				$clusterKey = $db->insert_id;
				//$logger->logDebug($sql);
				
				$sql = "INSERT INTO cluster_member (cluster_id, member) VALUES ";
				foreach($clusterMembers as $member) {
					//$sql .= "INSERT INTO cluster_member_$suffix SET cluster_id = $clusterKey, member = '$member';";
					$sql .= "($clusterKey, '$member'),";
					//$logger->logDebug($sql);
					
				}
				$sql = substr($sql, 0, strlen($sql) - 1);
				if(!$db->query($sql)) {
					$logger->logError("Error in $sql \n ".$db->error);
					exit;
				}
				
			}
		}
		$logger->logDebug("DB insert complete");
		
		if($printClustersAtEnd) {
			foreach($clusters as $key => $members) {
				$logger->logDebug($key);	
				$memberString = "";
				foreach($members as $member) {
					$memberString .= $usernames[$member]." ";
				}
				$logger->logDebug($memberString);	
				$logger->logDebug(""); //for readability
			}	
		}
	}

	catch (Exception $e) {
		echo 'Caught exception: ',  $e->getMessage(), "\n";
	}	
}

$logger->logInfo('Run time: '.round($logger->getElapsedTime(), 3).' seconds', __FILE__, __LINE__);

?>