<?php
	include 'definitions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
<meta http-equiv="refresh" content="10">
<link rel="stylesheet" type="text/css" href="master.css">
</head>
<body>
<?php
include 'Y2020_info.php';

$refresh = ($argv[1] == 'refresh' );
$show_config = $_GET['showconfig'];

$scored_keys = [
	'CERTIFIED YES',
	'CERTIFIED NO',
	'YES',
	'NO',
	'UP',
	'LOW',
];

$mcats = $competition['mcats'];

echo
'<h1>' . $competition['name'] . '</h1>
';

// Making demonstration categories
$demos = [];
foreach( $mcats as $mcat_name => $cats ) {
	foreach( $cats as $cat_name => $cat ) {
		if( array_key_exists( 'parts', $cat ) ) {
			switch( count($cat['parts']) ) {
			case 0:
				if( !$cat['jobid'] > 0 ) {
					unset($mcats[$mcat_name][$cat_name]);// remove unparticipated category
				}
				break;
			case 1:
				$demos[$cat_name] = $cat;
				unset($cat);
				break;
			}
		}
	}
}
$dmcat =& $mcats['Demonstrations'];
foreach( $demos as $cat_name => $cat ) {
	$dmcat[$cat_name] = $cat;
}

// Main display
foreach( array_keys($mcats) as $mcatname ) {
	$total_done = 0;
	$total_togo = 0;
	$total_cpu = 0;
	$total_time = 0;
	echo
'<h2>' . $mcatname . '</h2>
';
	$cats = $mcats[$mcatname];
	$table = [];
	$tools = [];
	echo
'<table>
 <tr>
  <th class=category>category
  <th class=ranking>ranking
';
	foreach( array_keys($cats) as $catname ) {
		$cat =& $cats[$catname];
		$type = $cat['type'];
		$jobid = $cat['jobid'];
		if( !$jobid ) {
			echo
' <tr class=' . $class . '>
  <td class=category>'.$catname;
			continue;
		}
		$cat_done = 0;
		$cat_togo = 0;
		$cat_cpu = 0;
		$cat_time = 0;
		// if job html exists, use it
		$jobpath = 'caches/'.$type.'_'.$jobid.'.html';
		if( ! file_exists($jobpath) ) {
			// creating job specific php file
			$jobfile = $type.'_'.$jobid.'.php';
			$jobpath = 'caches/'.$jobfile;
			if( ! file_exists($jobpath) ) {
				$file = fopen($jobpath,'w');
				fwrite( $file,
'<?php
	$competitionname = '. str2str($competition['name']) . ';
	$jobname = ' . str2str($catname) . ';
	$jobid = ' . $jobid . ';
	chdir("..");
	include \'' . type2php($type) .'\';
?>'
				); 
				fclose($file);
			}
		}
		if( $refresh ) {
			$ret = system( 'cd caches; php -f "'. $jobfile . '"; cd ..');
		}

		$init = false;
		$togo = 0;
		$conflicts = 0;
		$best = [ 'score' => 1, 'time' => INF ];
		foreach( $scored_keys as $key ) {
			$best[$key] = 1;
		}

		// checking cached score file and making ranking
		$fname = jobid2scorefile($jobid); 
		if( file_exists($fname) ) {
			$init = true;
			$solvers = json_decode(file_get_contents($fname),TRUE);
			uasort($solvers, function($s,$t) { return $s['score'] < $t['score'] ? 1 : -1; } );
			foreach( $solvers as $s ) {
				$togo += $s['togo'];
				$conflicts += $s['conflicts'];
				foreach( $scored_keys as $key ) {
					$best[$key] = max($best[$key], $s[$key]);
				}
				$best['time'] = min($best['time'], $s['time']);
			}
		}
		if( !$init || $togo > 0 ) {
			$class = 'incomplete';
		} else {
			$class = 'complete';
			$jobpath .= '?complete=1';
		}
		echo
' <tr class=' . $class . '>
  <td class=category>
   <a href="' . $jobpath . '">' . $catname . '</a>
   <a class=starexecid href="' . jobid2url($jobid) . '">' . $jobid . '</a>
';
		if( $init ) {
			if( $conflicts > 0 ) {
				echo
'<a class=conflict href="' . $jobpath . '#conflict">conflict</a>
';
			} 
			echo
'  <td class=ranking>
';
			$prev_score = $best['score'];
			$rank = 1;
			$count = 0;
			foreach( $solvers as $s ) {
				$score = $s['score'];
				$togo = $s['togo'];
				$done = $s['done'];
				$cpu = $s['cpu'];
				$time = $s['time'];
				$certtime = $s['certtime'];
				$conflicts = $s['conflicts'];
				$name = $s['solver'];
				$id = $s['solverid'];
				$config = $s['config'];
				$configid = $s['configid'];
				$url = solverid2url($id);
				$count += 1;
				if( $prev_score > $score ) {
					$rank = $count;
				}
				$prev_score = $score;
				echo
'   <span class='. ( $rank == 1 ? 'best' : '' ) . 'solver>
    ' . $rank . '. <a href="'. $url . '">'. $name . '</a>';
				if( $show_config ) {
					echo '
     <a class=config href="' . configid2url($configid) . '">'. $config . '</a>';
				}
				echo '
    <span class=score>';
				foreach( $scored_keys as $key ) {
					if( array_key_exists( $key, $s ) ) {
						$subscore = $s[$key];
						echo '<span '. result2style( $key, $subscore == $best[$key] ) . '>'. $key . ':' . $subscore . '</span>, ';
					}
				}
				echo
'<span class='.( $time == $best['time'] ? 'besttime' : 'time' ).'>TIME:'.seconds2str($time).'</span>';
				if( $certtime != 0 ) {
					echo
', <span class=time>Certification:'.seconds2str($certtime).'</span>';
				}
				echo
'</span>
';
				if( $togo > 0 ) {
					echo
'   <span class=togo>, ' . $togo . ' to go</span>';
				}
				echo
'   </span><br/>
';
				$cat_cpu += $cpu;
				$cat_time += $time;
				$cat_done += $done;
				$cat_togo += $togo;
				$total_cpu += $cpu;
				$total_time += $time;
				$total_done += $done;
				$total_togo += $togo;
			}
		}
		if( $cat_togo > 0 ) {
			echo
' <td>' . $cat_done . '/' . ($cat_done + $cat_togo) . '
';
		}
	}
	echo
'</table>
<p>Progress: ' . $total_done . ($total_done + $total_togo) .
', CPU Time: ' . seconds2str($total_cpu).
', Node Time: ' . seconds2str($total_time) . '</p>
';
}

?>

</body>
</html>
