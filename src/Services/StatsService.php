<?php
namespace App\Services;

class StatsService {
	// functions.php
	public function getCurrentWeek() {
		//get the current week number
		global $mysqli;
		$nowSql = phppickem_now_eastern_sql();
		$sql = "select distinct weekNum from " . DB_PREFIX . "schedule where " . $nowSql . " < gameTimeEastern order by weekNum limit 1";
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			return $row['weekNum'];
		} else {
			$sql = "select max(weekNum) as weekNum from " . DB_PREFIX . "schedule";
			$query2 = $mysqli->query($sql);
			if ($query2->num_rows > 0) {
				$row = $query2->fetch_assoc();
				return $row['weekNum'];
			}
			$query2->free();
		}
		$query->free();
		die('Error getting current week: ' . $mysqli->error);
	}
	
	public function getCutoffDateTime($week) {
		//get the cutoff date for a given week
		global $mysqli;
		$week = (int)$week;
		if ($week <= 0) {
			die('Error getting cutoff date: invalid week');
		}
		$sql = "select gameTimeEastern from " . DB_PREFIX . "schedule where weekNum = " . $week . " and DATE_FORMAT(gameTimeEastern, '%W') = 'Sunday' order by gameTimeEastern limit 1;";
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			return $row['gameTimeEastern'];
		}
		$query->free();
		die('Error getting cutoff date: ' . $mysqli->error);
	}
	
	public function getFirstGameTime($week) {
		//get the first game time for a given week
		global $mysqli;
		$sql = "select gameTimeEastern from " . DB_PREFIX . "schedule where weekNum = " . $week . " order by gameTimeEastern limit 1";
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			return $row['gameTimeEastern'];
		}
		$query->free();
		die('Error getting first game time: ' . $mysqli->error);
	}

	public function getLastGameTime($week) {
		//get the last game time for a given week
		global $mysqli;
		$sql = "select gameTimeEastern from " . DB_PREFIX . "schedule where weekNum = " . $week . " order by gameTimeEastern desc limit 1";
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			return $row['gameTimeEastern'];
		}
		$query->free();
		die('Error getting last game time: ' . $mysqli->error);
	}
	
	public function getPickID($gameID, $userID) {
		//get the pick id for a particular game
		global $mysqli;
		$sql = "select pickID from " . DB_PREFIX . "picks where gameID = " . $gameID . " and userID = " . $userID;
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			return $row['pickID'];
		} else {
			return false;
		}
		$query->free();
		die('Error getting pick id: ' . $mysqli->error);
	}
	
	public function getGameIDByTeamName($week, $teamName) {
		//get the pick id for a particular game
		global $mysqli;
		$sql = "select gameID ";
		$sql .= "from " . DB_PREFIX . "schedule s ";
		$sql .= "inner join " . DB_PREFIX . "teams t1 on s.homeID = t1.teamID ";
		$sql .= "inner join " . DB_PREFIX . "teams t2 on s.visitorID = t2.teamID ";
		$sql .= "where weekNum = " . $week;
		$sql .= " and ((t1.city = '" . $teamName . "' or t1.displayName = '" . $teamName . "') or (t2.city = '" . $teamName . "' or t2.displayName = '" . $teamName . "'))";
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			return $row['gameID'];
		} else {
			return false;
		}
		$query->free();
		die('Error getting game id: ' . $mysqli->error);
	}
	
	public function getGameIDByTeamID($week, $teamID) {
		//get the pick id for a particular game
		global $mysqli;
		$sql = "select gameID ";
		$sql .= "from " . DB_PREFIX . "schedule s ";
		$sql .= "inner join " . DB_PREFIX . "teams t1 on s.homeID = t1.teamID ";
		$sql .= "inner join " . DB_PREFIX . "teams t2 on s.visitorID = t2.teamID ";
		$sql .= "where weekNum = " . $week;
		$sql .= " and (t1.teamID = '" . $teamID . "' or t2.teamID = '" . $teamID . "')";
		//echo $sql . "\n\n";
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			return $row['gameID'];
		} else {
			return false;
		}
		$query->free();
		die('Error getting game id: ' . $mysqli->error);
	}
	
	public function getUserPicks($week, $userID) {
		//gets user picks for a given week
		global $mysqli;
		$picks = array();
		$sql = "select p.* ";
		$sql .= "from " . DB_PREFIX . "picks p ";
		$sql .= "inner join " . DB_PREFIX . "schedule s on p.gameID = s.gameID ";
		$sql .= "where s.weekNum = " . $week . " and p.userID = " . $userID . ";";
		$query = $mysqli->query($sql);
		while ($row = $query->fetch_assoc()) {
			$picks[$row['gameID']] = array('pickID' => $row['pickID'], 'points' => $row['points']);
		}
		$query->free();
		return $picks;
	}
	
	public function getUserScore($week, $userID) {
		global $mysqli, $user;
	
		$score = 0;
	
		//get array of games
		$games = array();
		$sql = "select * from " . DB_PREFIX . "schedule where weekNum = " . $week . " order by gameTimeEastern, gameID";
		$query = $mysqli->query($sql);
		while ($row = $query->fetch_assoc()) {
			$games[$row['gameID']]['gameID'] = $row['gameID'];
			$games[$row['gameID']]['homeID'] = $row['homeID'];
			$games[$row['gameID']]['visitorID'] = $row['visitorID'];
			if ((int)$row['homeScore'] > (int)$row['visitorScore']) {
				$games[$row['gameID']]['winnerID'] = $row['homeID'];
			}
			if ((int)$row['visitorScore'] > (int)$row['homeScore']) {
				$games[$row['gameID']]['winnerID'] = $row['visitorID'];
			}
		}
		$query->free();
	
		//loop through player picks & calculate score
		$sql = "select p.userID, p.gameID, p.pickID, p.points ";
		$sql .= "from " . DB_PREFIX . "picks p ";
		$sql .= "inner join " . DB_PREFIX . "users u on p.userID = u.userID ";
		$sql .= "inner join " . DB_PREFIX . "schedule s on p.gameID = s.gameID ";
		$sql .= "where s.weekNum = " . $week . " and u.userID = " . $user->userID . " ";
		$sql .= "order by u.lastname, u.firstname, s.gameTimeEastern";
		$query = $mysqli->query($sql);
		while ($row = $query->fetch_assoc()) {
			if (!empty($games[$row['gameID']]['winnerID']) && $row['pickID'] == $games[$row['gameID']]['winnerID']) {
				//player has picked the winning team
				$score++;
			}
		}
		$query->free();
	
		return $score;
	}
	
	public function getGameTotal($week) {
		//get the total number of games for a given week
		global $mysqli;
		$sql = "select count(gameID) as gameTotal from " . DB_PREFIX . "schedule where weekNum = " . $week;
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			return $row['gameTotal'];
		}
		$query->free();
		die('Error getting game total: ' . $mysqli->error);
	}
	
	public function gameIsLocked($gameID) {
		//find out if a game is locked
		global $mysqli, $cutoffDateTime;
		$nowSql = phppickem_now_eastern_sql();
		$sql = "select (" . $nowSql . " > gameTimeEastern or " . $nowSql . " > '" . $cutoffDateTime . "') as expired from " . DB_PREFIX . "schedule where gameID = " . $gameID;
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			return $row['expired'];
		}
		$query->free();
		die('Error getting game locked status: ' . $mysqli->error);
	}
	
	public function hidePicks($userID, $week) {
		//find out if user is hiding picks for a given week
		global $mysqli;
		$sql = "select showPicks from " . DB_PREFIX . "picksummary where userID = " . $userID . " and weekNum = " . $week;
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$row = $query->fetch_assoc();
			return (($row['showPicks']) ? 0 : 1);
		}
		$query->free();
		return 0;
	}
	
	public function getLastCompletedWeek() {
		global $mysqli;
		$lastCompletedWeek = 0;
		$nowSql = phppickem_now_eastern_sql();
		$sql = "select s.weekNum, max(s.gameTimeEastern) as lastGameTime,";
		$sql .= " (select count(*) from " . DB_PREFIX . "schedule where weekNum = s.weekNum and (homeScore is NULL or visitorScore is null)) as scoresMissing ";
		$sql .= "from " . DB_PREFIX . "schedule s ";
		$sql .= "where s.gameTimeEastern < " . $nowSql . " ";
		$sql .= "group by s.weekNum ";
		$sql .= "order by s.weekNum";
		//echo $sql;
		$query = $mysqli->query($sql);
		while ($row = $query->fetch_assoc()) {
			if ((int)$row['scoresMissing'] == 0) {
				$lastCompletedWeek = (int)$row['weekNum'];
			}
		}
		if ($query) {
			$query->free();
		}
		return $lastCompletedWeek;
	}
	
	public function calculateStats() {
		global $mysqli;
		$weekStats = array();
		$playerTotals = array();
		$possibleScoreTotal = 0;
		//get latest week with all entered scores
		$lastCompletedWeek = $this->getLastCompletedWeek();
	
		//loop through weeks
		for ($week = 1; $week <= $lastCompletedWeek; $week++) {
			//get array of games
			$games = array();
			$sql = "select * from " . DB_PREFIX . "schedule where weekNum = " . $week . " order by gameTimeEastern, gameID";
			$query = $mysqli->query($sql);
			while ($row = $query->fetch_assoc()) {
				$games[$row['gameID']]['gameID'] = $row['gameID'];
				$games[$row['gameID']]['homeID'] = $row['homeID'];
				$games[$row['gameID']]['visitorID'] = $row['visitorID'];
				if ((int)$row['homeScore'] > (int)$row['visitorScore']) {
					$games[$row['gameID']]['winnerID'] = $row['homeID'];
				}
				if ((int)$row['visitorScore'] > (int)$row['homeScore']) {
					$games[$row['gameID']]['winnerID'] = $row['visitorID'];
				}
			}
			if ($query) {
				$query->free();
			}
	
			//get array of player picks
			$playerPicks = array();
			$playerWeeklyTotals = array();
			$sql = "select p.userID, p.gameID, p.pickID, p.points, u.firstname, u.lastname, u.userName ";
			$sql .= "from " . DB_PREFIX . "picks p ";
			$sql .= "inner join " . DB_PREFIX . "users u on p.userID = u.userID ";
			$sql .= "inner join " . DB_PREFIX . "schedule s on p.gameID = s.gameID ";
			$sql .= "where s.weekNum = " . $week . " and u.userName <> 'admin' ";
			$sql .= "order by u.lastname, u.firstname, s.gameTimeEastern";
			$query = $mysqli->query($sql);
			while ($row = $query->fetch_assoc()) {
				$playerPicks[$row['userID'] . $row['gameID']] = $row['pickID'];
				$playerWeeklyTotals[$row['userID']]['week'] = $week;
				if (!isset($playerWeeklyTotals[$row['userID']]['score'])) {
					$playerWeeklyTotals[$row['userID']]['score'] = 0;
				}
				if (!isset($playerTotals[$row['userID']]['wins'])) {
					$playerTotals[$row['userID']]['wins'] = 0;
				}
				if (!isset($playerTotals[$row['userID']]['score'])) {
					$playerTotals[$row['userID']]['score'] = 0;
				}
				$playerTotals[$row['userID']]['wins'] += 0;
				$playerTotals[$row['userID']]['name'] = $row['firstname'] . ' ' . $row['lastname'];
				$playerTotals[$row['userID']]['userName'] = $row['userName'];
				if (!empty($games[$row['gameID']]['winnerID']) && $row['pickID'] == $games[$row['gameID']]['winnerID']) {
					//player has picked the winning team
					$playerWeeklyTotals[$row['userID']]['score'] += 1;
					$playerTotals[$row['userID']]['score'] += 1;
				} else {
					$playerWeeklyTotals[$row['userID']]['score'] += 0;
					$playerTotals[$row['userID']]['score'] += 0;
				}
			}
			if ($query) {
				$query->free();
			}
	
			//get winners & highest score for current week
			$highestScore = 0;
			arsort($playerWeeklyTotals);
			foreach($playerWeeklyTotals as $playerID => $stats) {
				if ($stats['score'] > $highestScore) $highestScore = $stats['score'];
				if ($stats['score'] < $highestScore) break;
				$weekStats[$week]['winners'][] = $playerID;
				$playerTotals[$playerID]['wins'] += 1;
			}
			$weekStats[$week]['highestScore'] = $highestScore;
			$weekStats[$week]['possibleScore'] = $this->getGameTotal($week);
			$weekStats[$week]['label'] = 'Week ' . $week;
			$weekStats[$week]['sortOrder'] = (int)$week;
			$possibleScoreTotal += $weekStats[$week]['possibleScore'];
		}

		// playoff stats by round (do not add to possibleScoreTotal)
		$playoffRoundLabels = array(
			1 => 'WC',
			2 => 'DIV',
			3 => 'CONF',
			4 => 'SB'
		);
		$playoffWeekStats = array();
		$playoffGames = array();
		$sql = "select playoffGameID, roundNum, homeID, visitorID, homeScore, visitorScore ";
		$sql .= "from " . DB_PREFIX . "playoff_schedule ";
		$sql .= "where is_bye = 0 and homeScore is not null and visitorScore is not null ";
		$query = $mysqli->query($sql);
		while ($row = $query->fetch_assoc()) {
			$homeScore = (int)$row['homeScore'];
			$visitorScore = (int)$row['visitorScore'];
			if ($homeScore + $visitorScore <= 0) {
				continue;
			}
			$winnerID = ($homeScore > $visitorScore) ? $row['homeID'] : $row['visitorID'];
			$playoffGames[(int)$row['playoffGameID']] = array(
				'roundNum' => (int)$row['roundNum'],
				'winnerID' => $winnerID
			);
			if (!isset($playoffWeekStats[(int)$row['roundNum']])) {
				$playoffWeekStats[(int)$row['roundNum']] = array('winners' => array(), 'highestScore' => 0, 'possibleScore' => 0, 'scores' => array());
			}
			$playoffWeekStats[(int)$row['roundNum']]['possibleScore'] += 1;
		}
		if ($query) {
			$query->free();
		}

		if (!empty($playoffGames)) {
			$sql = "select p.userID, p.gameID, p.pickTeamID, u.firstname, u.lastname, u.userName ";
			$sql .= "from " . DB_PREFIX . "playoff_picks p ";
			$sql .= "inner join " . DB_PREFIX . "users u on p.userID = u.userID ";
			$sql .= "where u.userName <> 'admin' ";
			$query = $mysqli->query($sql);
			while ($row = $query->fetch_assoc()) {
				$gameId = (int)$row['gameID'];
				if (!isset($playoffGames[$gameId])) {
					continue;
				}
				$roundNum = $playoffGames[$gameId]['roundNum'];
				$winnerID = $playoffGames[$gameId]['winnerID'];
				if (!isset($playoffWeekStats[$roundNum]['scores'][$row['userID']])) {
					$playoffWeekStats[$roundNum]['scores'][$row['userID']] = 0;
				}
				if ($row['pickTeamID'] == $winnerID) {
					$playoffWeekStats[$roundNum]['scores'][$row['userID']] += 1;
				}
				if (!isset($playerTotals[$row['userID']]['wins'])) {
					$playerTotals[$row['userID']]['wins'] = 0;
				}
				if (!isset($playerTotals[$row['userID']]['score'])) {
					$playerTotals[$row['userID']]['score'] = 0;
				}
				$playerTotals[$row['userID']]['name'] = $row['firstname'] . ' ' . $row['lastname'];
				$playerTotals[$row['userID']]['userName'] = $row['userName'];
			}
			if ($query) {
				$query->free();
			}
		}
		foreach ($playoffWeekStats as $roundNum => $stats) {
			if (!empty($stats['scores'])) {
				arsort($stats['scores']);
				$highestScore = reset($stats['scores']);
				$playoffWeekStats[$roundNum]['highestScore'] = $highestScore;
				foreach ($stats['scores'] as $userID => $score) {
					if ($score < $highestScore) break;
					$playoffWeekStats[$roundNum]['winners'][] = $userID;
					if (isset($playerTotals[$userID]['wins'])) {
						$playerTotals[$userID]['wins'] += 1;
					}
				}
				$key = 'P' . (int)$roundNum;
				$label = isset($playoffRoundLabels[$roundNum]) ? $playoffRoundLabels[$roundNum] : ('P' . $roundNum);
				$weekStats[$key] = array(
					'winners' => $playoffWeekStats[$roundNum]['winners'],
					'highestScore' => $playoffWeekStats[$roundNum]['highestScore'],
					'possibleScore' => $playoffWeekStats[$roundNum]['possibleScore'],
					'label' => $label,
					'sortOrder' => 100 + (int)$roundNum,
					'isPlayoff' => true
				);
			}
		}
		return array(
			'weekStats' => $weekStats,
			'playerTotals' => $playerTotals,
			'possibleScoreTotal' => $possibleScoreTotal
		);
	}
	
	public function rteSafe($strText) {
		//returns safe code for preloading in the RTE
		$tmpString = $strText;
	
		//convert all types of single quotes
		$tmpString = str_replace(chr(145), chr(39), $tmpString);
		$tmpString = str_replace(chr(146), chr(39), $tmpString);
		$tmpString = str_replace("'", "&#39;", $tmpString);
	
		//convert all types of double quotes
		$tmpString = str_replace(chr(147), chr(34), $tmpString);
		$tmpString = str_replace(chr(148), chr(34), $tmpString);
	//	$tmpString = str_replace("\"", "\"", $tmpString);
	
		//replace carriage returns & line feeds
		$tmpString = str_replace(chr(10), " ", $tmpString);
		$tmpString = str_replace(chr(13), " ", $tmpString);
	
		return $tmpString;
	}
	
	//the following function was found at http://www.codingforums.com/showthread.php?t=71904
	public function sort2d ($array, $index, $order='asc', $natsort=FALSE, $case_sensitive=FALSE) {
		if (is_array($array) && count($array) > 0) {
			foreach(array_keys($array) as $key) {
				$temp[$key]=$array[$key][$index];
			}
			if(!$natsort) {
				($order=='asc')? asort($temp) : arsort($temp);
			} else {
				($case_sensitive)? natsort($temp) : natcasesort($temp);
				if($order!='asc') {
					$temp=array_reverse($temp,TRUE);
				}
			}
			foreach(array_keys($temp) as $key) {
				(is_numeric($key))? $sorted[]=$array[$key] : $sorted[$key]=$array[$key];
			}
			return $sorted;
		}
		return $array;
	}
	
	public function getTeamRecord($teamID) {
		global $mysqli;
	
		$sql = "select weekNum, (homeScore > visitorScore) as gameWon, (homeScore = visitorScore) as gameTied ";
		$sql .= "from " . DB_PREFIX . "schedule ";
		$sql .= "where (homeScore not in(null, '0') and visitorScore not in(null, '0'))";
		$sql .= " and homeID = '" . $teamID . "' ";
		$sql .= "union ";
		$sql .= "select weekNum, (homeScore < visitorScore) as gameWon, (homeScore = visitorScore) as gameTied ";
		$sql .= "from " . DB_PREFIX . "schedule ";
		$sql .= "where (homeScore not in(null, '0') and visitorScore not in(null, '0'))";
		$sql .= " and visitorID = '" . $teamID . "' ";
		$sql .= "order by weekNum";
		//echo $sql;
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$wins = 0;
			$losses = 0;
			$ties = 0;
			while ($row = $query->fetch_assoc()) {
				if ($row['gameTied']) {
					$ties++;
				} else if ($row['gameWon']) {
					$wins++;
				} else {
					$losses++;
				}
			}
			return $wins . '-' . $losses . '-' . $ties;
		} else {
			return '';
		}
		$query->free();
	}
	
	public function getTeamStreak($teamID) {
		global $mysqli;
	
		$sql = "select weekNum, (homeScore > visitorScore) as gameWon, (homeScore = visitorScore) as gameTied ";
		$sql .= "from " . DB_PREFIX . "schedule ";
		$sql .= "where (homeScore not in(null, '0') and visitorScore not in(null, '0'))";
		$sql .= " and homeID = '" . $teamID . "' ";
		$sql .= "union ";
		$sql .= "select weekNum, (homeScore < visitorScore) as gameWon, (homeScore = visitorScore) as gameTied ";
		$sql .= "from " . DB_PREFIX . "schedule ";
		$sql .= "where (homeScore not in(null, '0') and visitorScore not in(null, '0'))";
		$sql .= " and visitorID = '" . $teamID . "' ";
		$sql .= "order by weekNum";
		//echo $sql;
		$query = $mysqli->query($sql);
		if ($query->num_rows > 0) {
			$prev = '';
			$iStreak = 0;
			while ($row = $query->fetch_assoc()) {
				if ($row['gameTied']) {
					$current = 'T';
				} else if ($row['gameWon']) {
					$current = 'W';
				} else {
					$current = 'L';
				}
				if ($prev == $current) {
					$iStreak++;
				} else {
					$iStreak = 1;
				}
				$prev = $current;
			}
			return $current . ' ' . $iStreak;
		} else {
			return '';
		}
		$query->free();
	}
}
