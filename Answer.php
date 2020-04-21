<?php
/**
 * Grab Snaffles and try to throw them through the opponent's goal!
 * Move towards a Snaffle to grab it and use your team id to determine towards where you need to throw it.
 * Use the Wingardium spell to move things around at your leisure, the more magic you put it, the further they'll move.
 **/

// $myTeamId: if 0 you need to score on the right of the map, if 1 you need to score on the left
class Game{
	private $time;

	function __construct ()
	{
		$this->time = 1;
	}

	public function getTime()
	{
		return $this->time;
	}

	public function incTime()
	{
		$this->time++;
	}
}

class Entity
{
	static $myTeamId;
	protected $position;
	protected $id;
	protected $entityType;
	protected $velocity;
	protected $state;

	function __construct ($x, $y, $entityId=-1, $entityType=-1, $vx=-1, $vy=-1, $state=0) {
		$this->position=[$x,$y];
		$this->id=$entityId;
		$this->entityType=$entityType;
		$this->velocity=[$vx,$vy];
		$this->state=$state;
	}

	static public function setId(int $myTeamId)
	{
		self::$myTeamId=$myTeamId;
	}

	public function getVelocity()
	{
		return $this->velocity;
	}

	public function getState()
	{
		return $this->state;
	}

	public function getPosition()
	{
		return $this->position;
	}

	public function getId()
	{
		return $this->id;
	}

	public function getMySide(): array
	{
		if ($this->getPosition()[1]<3750) {
			return [0, 3750];
		} else {
			return [3750, 7500];
		}
	}

	public function isFront(Entity $entity, int $offset=0)
	{
		if (self::$myTeamId===0) {
			$reverse = 1;
		} else {
			$reverse = -1;
		}
		if ($reverse*($this->position[0]-$entity->position[0])+$offset<0) {
			return True;
		} else {
			return False;
		}
	}

	public function isBehind(Entity $entity, int $offset=0)
	{
		if (self::$myTeamId===0) {
			$reverse = 1;
		} else {
			$reverse = -1;
		}
		if ($reverse*($this->position[0]-$entity->position[0])-$offset>0) {
			return True;
		} else {
			return False;
		}
	}

}

class Wizard extends Entity
{
	static $myGoal;
	static $opponentGoal;
	static $wizards;
	static $opponents;
	static $snaffles;
	static $bludgers;
	static $myScore;
	static $myMagic;
	static $opponentScore;
	static $opponentMagic;

	static public function setGoals(array $my, array $opponent)
	{
		self::$myGoal=new Entity($my[0],$my[1]);
		self::$opponentGoal=new Entity($opponent[0],$opponent[1]);
	}

	static public function setEntities(array $wizards, array $opponents, array $snaffles, array $bludgers)
	{
		self::$wizards=$wizards;
		self::$opponents=$opponents;
		self::$snaffles=$snaffles;
		self::$bludgers=$bludgers;
	}

	static public function setParams($myScore,$myMagic,$opponentScore,$opponentMagic)
	{
		self::$myScore=$myScore;
		self::$myMagic=$myMagic;
		self::$opponentScore=$opponentScore;
		self::$opponentMagic=$opponentMagic;
	}

	static public function getMyAreaEntities(array $entities)
	{
		if (self::$myTeamId===0) {
			$reverse = 1;
		} else {
			$reverse = -1;
		}
		$results = [];
		foreach ($entities as $entity) {
			if ($reverse*(8000-$entity->getPosition()[0]) > 0) {
				array_push($results, $entity);
			}
		}
		return $results;
	}

	public function getNearestSnaffle(array $snaffles)
	{
		$min = 100000000;
		$nearestSnaffle = NULL;
		foreach ($snaffles as $snaffle) {
			if (distance($this, $snaffle) < $min) {
				$min = distance($this, $snaffle);
				$nearestSnaffle = $snaffle;
			}
		}
		return $nearestSnaffle;
	}

	public function isLose()
	{
		if (self::$myTeamId===0) {
			$reverse = 1;
		} else {
			$reverse = -1;
		}

		$snaffles = self::$snaffles;
		$wining = [];
		$losing = [];
		foreach($snaffles as $snaffle) {
			if (($this->getPosition()[0]-$snaffle->getPosition()[0])*$reverse > 0) {
				array_push($losing, $snaffle);
			} else {
				array_push($wining, $snaffle);
			}
		}
		if (count($losing)+self::$opponentScore>count($wining)+self::$myScore){
			return True;
		} else {
			return False;
		}
	}

	/*
	public function moveToNearestSnaffle()
	{
		if (self::$myTeamId===0) {
			$reverse = 1;
		} else {
			$reverse = -1;
		}
		if ((8000-$this->getPosition()[0])*$reverse > 0) {
		    $nearestSnaffle = $this->getNearestSnaffle(self::$snaffles);
		    echo sprintf("MOVE %d %d %d\n", $nearestSnaffle->getPosition()[0], $nearestSnaffle->getPosition()[1], controllableSpeed($this, $nearestSnaffle));
		} else {
			return $this->moveToNearestSnaffleOptimally();
		}
	}
	 */

	public function moveToNearestEntity(array $entities)
	{
		$min = 10000000;
		foreach ($entities as $entity) {
			if (distance($this, $entity) < $min) {
				$min = distance($this, $entity);
				$nearest = $entity;
			}
		}
		
		echo sprintf("MOVE %d %d %d\n", $nearest->getPosition()[0]+$nearest->getVelocity()[0], $nearest->getPosition()[1]+$nearest->getVelocity()[1], controllableSpeed($this, $nearest));
		return $nearest;
	}

	public function moveToNearestSnaffle(Entity $partnerTarget = NULL)//パートナーのターゲットを入れたらそれを避ける
	{
		if (self::$myTeamId===0) {
			$reverse = 1;
		} else {
			$reverse = -1;
		}

		$snaffles = self::$snaffles;
		if ($partnerTarget !== NULL and count($snaffles)>1) $snaffles = $this->removeDuplicatedSnaffle($snaffles, $partnerTarget);
		$frontSnaffles=[];
		foreach ($snaffles as $snaffle) {
			if ($this->isFront($snaffle, -1500) and abs($this->getPosition()[1]-$snaffle->getPosition()[1])<3000) {
				array_push($frontSnaffles, $snaffle);
			}
		}
		$snaffles = self::$snaffles;
		if ($partnerTarget !== NULL and count($snaffles)>1) $snaffles = $this->removeDuplicatedSnaffle($snaffles, $partnerTarget);

		$nearestSnaffle = $this->getNearestSnaffle($snaffles);
		if (count($frontSnaffles) === 0 or distance($nearestSnaffle, $this) < 800) {
			$nearestOpponent = getNearestEntity($nearestSnaffle, self::$opponents);
			if (distance($nearestOpponent, $nearestSnaffle)<distance($this, $nearestSnaffle) and (8000 - $nearestOpponent->getPosition()[0])*$reverse > 0){
			    error_log(var_export('A', true));
				return $this->keepSocialDistanceFrom($nearestSnaffle);
			}				
			else {
				echo sprintf("MOVE %d %d %d\n", $nearestSnaffle->getPosition()[0], $nearestSnaffle->getPosition()[1], controllableSpeed($this, $nearestSnaffle));
			return;
			}
		}
		$min = 1000000000;
		foreach ($frontSnaffles as $snaffle) {
			if (abs($this->getPosition()[0]-$snaffle->getPosition()[0]) < $min) {
				$min = abs($this->getPosition()[0]-$snaffle->getPosition()[0]);
				$nearest = $snaffle;
			}
		}

		foreach (self::$wizards as $wizard) {
			if ($wizard->getId() !== $this->getId()) $partner=$wizard;
		}
		$nearestOpponent = getNearestEntity($nearestSnaffle, self::$opponents);
		if (distance($nearestOpponent, $nearest)<distance($this, $nearest) and ($this->getPosition()[0]-$partner->getPosition()[0])*$reverse < 0){
			error_log(var_export('B', true));
			return $this->keepSocialDistanceFrom($nearest);
		}			
		else {
			echo sprintf("MOVE %d %d %d\n", $nearest->getPosition()[0], $nearest->getPosition()[1], controllableSpeed($this, $nearest));
		}
	}

	private function removeDuplicatedSnaffle(array $snaffles, Entity $partnerTarget)
	{
	    $results = $snaffles;
		foreach ($snaffles as $key => $snaffle) {
			if ($snaffle->getId() === $partnerTarget->getId()) {
				unset($results[$key]);
				break;
			}
		}
		return $results;
	}
	
	public function moveToSecondaryNearestSnaffle()
	{
		if (count(self::$snaffles) < 2) {
		    $nearestSnaffle = $this->getNearestSnaffle(self::$snaffles);
		    echo sprintf("MOVE %d %d %d\n", $nearestSnaffle->getPosition()[0], $nearestSnaffle->getPosition()[1], controllableSpeed($this, $nearestSnaffle));
		} else {
			$nearestSnaffle = $this->getNearestSnaffle(self::$snaffles);
			$snaffles = self::$snaffles;
			foreach ($snaffles as $key => $snaffle) {
				if ($snaffle->getId() === $nearestSnaffle->getId()) {
					unset($snaffles[$key]);
				}
			}
			$secondaryNearestSnaffle = $this->getNearestSnaffle($snaffles);
			echo sprintf("MOVE %d %d %d\n", $secondaryNearestSnaffle->getPosition()[0], $secondaryNearestSnaffle->getPosition()[1], controllableSpeed($this, $nearestSnaffle));
		}
	}

	public function moveToOpposite(Entity $entity)
	{
		echo sprintf("MOVE %d %d %d\n", intval(2*$this->getPosition()[0]-$entity->getPosition()[0]), intval(2*$this->getPosition()[1]-$entity->getPosition()[1]), controllableSpeed($this, $entity));
	}

	public function moveTo(Entity $entity)
	{
		echo sprintf("MOVE %d %d %d\n", $entity->getPosition()[0], $entity->getPosition()[1], controllableSpeed($this, $entity));
	}

	public function keepSocialDistanceFrom(Entity $entity)
	{
		$nearestOpponent = getNearestEntity($entity, self::$opponents);
		if (distance($nearestOpponent, $entity) > distance($this, $entity)) {
			echo sprintf("MOVE %d %d %d\n", $entity->getPosition()[0], $entity->getPosition()[1], controllableSpeed($this, $entity));
		} else {
			$toward = midPoint($entity, self::$myGoal, 2, 1);
			echo sprintf("MOVE %d %d %d\n", $toward->getPosition()[0], $toward->getPosition()[1], controllableSpeed($this, $toward));
		}
	}

	public function throwToOpponentGoal(int $power=500, $yOffset=0)
	{
		$opponents = self::$opponents;
		$opponents += self::$bludgers;
		$results = [];
		foreach ($opponents as $opponent) {
			if ($this->isFront($opponent, 1000) and distance($this, $opponent)<3000) {
				array_push($results, $opponent);
			}
		}
		$results = getEntityBetweenY($results, [$this->getPosition()[1]-500, $this->getPosition()[1]+500]);
		if (count($results) > 0) {
			$neaestOpponent = getNearestEntity($this, $results);
			$offset = intval(distance($this, $neaestOpponent));
			if ($this->getPosition()[1]<3750) $offset*=-1;
			echo sprintf("THROW %d %d %d\n", $neaestOpponent->getPosition()[0], $neaestOpponent->getPosition()[1]+$offset, $power);
		} else {

			$snaffles = self::$snaffles;
			$results = [];
			foreach ($snaffles as $snaffle) {
				if ($this->isFront($snaffle, 1000) and distance($this, $snaffle)<3000) {
					array_push($results, $snaffle);
				}
			}
		if (self::$myTeamId===0) {
			$reverse = 1;
		} else {
			$reverse = -1;
		}
			if (count($results) > 0 and (8000-$this->getPosition()[0])*$reverse<0) {
				$target = getNearestEntity($this, $results);
				echo sprintf("THROW %d %d %d\n", $target->getPosition()[0], $target->getPosition()[1], $power);
			} else {
				echo sprintf("THROW %d %d %d\n", self::$opponentGoal->getPosition()[0], self::$opponentGoal->getPosition()[1]+$yOffset, $power);
			}
		}
	}

	public function throwTo(Entity $entity, int $power=500, $offset=[0,0])
	{
		echo sprintf("THROW %d %d %d\n", $entity->position[0]+$offset[0], $entity->position[1]+$offset[1], $power);
	}

	public function castSpellTo(Entity $entity, Entity $target, int $magic=30)
	{
		$magic = min(self::$myMagic, $magic);
		echo sprintf("WINGARDIUM %d %d %d %d\n",$entity->id, $target->position[0], $target->position[1], $magic);
	}
}

class Opponent extends Entity
{
}

class Snaffle extends Entity
{
}

class Bludger extends Entity
{
}

function distance(Entity $a, Entity $b): float
{
	return
		array_sum(
			array_map(
				function($x, $y) {
					return abs($x - $y) ** 2;
				}, $a->getPosition(), $b->getPosition()
			)
		) ** (1/2);
}

function midPoint(Entity $a, Entity $b, int $m=1, int $n=1): Entity
{
	return new Entity(intval(($m*$a->getPosition()[0]+$n*$b->getPosition()[0])/($m+$n)),intval(($m*$a->getPosition()[1]+$n*$b->getPosition()[1])/($m+$n)));
}

function controllableSpeed(Entity $a, Entity $b): int
{
	if (distance($a, $b)>1000) {
		return 150;
	} else {
		return min(150, distance($a, $b)*(0.15)+30);
	}
}

function getNearestEntity(Entity $from, array $to, array $yOffset=[0, 7500])
{
	$min = 100000000;
	$nearest = NULL;
	foreach ($to as $entity) {
		if (distance($from, $entity) < $min and ($entity->getPosition()[1]>=$yOffset[0] and $entity->getPosition()[1]<=$yOffset[1])) {
			$min = distance($from, $entity);
			$nearest = $entity;
		}
	}
	return $nearest;
}

function getfarestEntity(Entity $from, array $to, array $yOffset=[0, 7500])
{
	$max = 0;
	$farest = NULL;
	foreach ($to as $entity) {
		if (distance($from, $entity) > $max and ($entity->getPosition()[1]>=$yOffset[0] and $entity->getPosition()[1]<=$yOffset[1])) {
			$max = distance($from, $entity);
			$farest = $entity;
		}
	}
	return $farest;
}

function getEntityBetweenY(array $entities, array $y)
{
	sort($y);
	$results = [];
	foreach ($entities as $entity) {
		if ($entity->getPosition()[1]>$y[0] and $entity->getPosition()[1]<$y[1]) {
			array_push($results, $entity);
		}
	}
	return $results;
}
function getEntityBetweenX(array $entities, array $x)
{
	sort($x);
	$results = [];
	foreach ($entities as $entity) {
		if ($entity->getPosition()[0]>$x[0] and $entity->getPosition()[0]<$x[1]) {
			array_push($results, $entity);
		}
	}
	return $results;
}

fscanf(STDIN, "%d", $myTeamId);

Wizard::setId($myTeamId);

if ($myTeamId===0) {
	Wizard::setGoals([0,3750],[16000,3750]);
	$dummyTarget = new Entity(Wizard::$myGoal->getPosition()[0] + 4000, 0);
	$reverse = 1;
} else {
	Wizard::setGoals([16000,3750],[0,3750]);
	$dummyTarget = new Entity(Wizard::$myGoal->getPosition()[0] - 4000, 0);
	$reverse = -1;
}


$game = new Game();
$ignoreFlag = 0;
// game loop
while (TRUE)
{
	$wizards=[];
	$opponents=[];
	$snaffles=[];
	$bludgers=[];
	fscanf(STDIN, "%d %d", $myScore, $myMagic);
	fscanf(STDIN, "%d %d", $opponentScore, $opponentMagic);
	// $entities: number of entities still in game
	fscanf(STDIN, "%d", $entities);
	for ($i = 0; $i < $entities; $i++)
	{
		// $entityId: entity identifier
		// $entityType: "WIZARD", "OPPONENT_WIZARD" or "SNAFFLE" or "BLUDGER"
		// $x: position
		// $y: position
		// $vx: velocity
		// $vy: velocity
		// $state: 1 if the wizard is holding a Snaffle, 0 otherwise. 1 if the Snaffle is being held, 0 otherwise. id of the last victim of the bludger.
		fscanf(STDIN, "%d %s %d %d %d %d %d", $entityId, $entityType, $x, $y, $vx, $vy, $state);
		if ($entityType === 'WIZARD') {
			array_push($wizards, new Wizard($x, $y, $entityId, $entityType, $vx, $vy, $state));
		}elseif ($entityType === 'OPPONENT_WIZARD') {
			array_push($opponents, new Opponent($x, $y, $entityId, $entityType, $vx, $vy, $state));
		}elseif ($entityType === 'SNAFFLE') {
			array_push($snaffles, new Snaffle($x, $y, $entityId, $entityType, $vx, $vy, $state));
		}elseif ($entityType === 'BLUDGER') {
			array_push($bludgers, new Bludger($x, $y, $entityId, $entityType, $vx, $vy, $state));
		}
		Wizard::setEntities($wizards, $opponents, $snaffles, $bludgers);
		Wizard::setParams($myScore,$myMagic,$opponentScore,$opponentMagic);
	}
	/**
	 * $wizards[$i]->throwToOpponentGoal(500); //相手のゴールに投げる
	 * $wizards[$i]->throwTo($wizards[$i xor 1]); //味方にパス
	 * $wizards[$i]->moveToNearestSnaffle(); //一番近くのsnaffleに近づく
	 * $wizards[$i]->moveTo(Entity); //Entityに近づく
	 * $wizards[$i]->keepSocialDistanceFrom($opponents[0]); //相手トゴールのあいだに動く
	 * $wizards[$i]->castSpellTo($opponents[0],100); //魔法を唱える(default:30)
	 * $wizards[$i]->throwTo($wizards[$i xor 1]);// パス
	 */
	$partnerMoveToTarget=NULL;
	$partnerCastSpellToTarget=NULL;
	$partnerCastSpellToSave = NULL;
	$partnerCastSpellToFinish = NULL;
	$partnerBlocking = NULL;
	for ($i=0; $i<2; $i++)
	{
		$opponentGoalNearestSnaffle=getNearestEntity(Wizard::$opponentGoal, $snaffles);//相手ゴールに一番近いSnaffleを取得
		$opponentGoalNearestOpponent=getNearestEntity(Wizard::$opponentGoal, $opponents);//相手ゴールに一番近いopponent
		$opponentGoalNearestWizard=getNearestEntity(Wizard::$opponentGoal, $wizards);//相手ゴールに一番近いwizard
		$nearestSnaffle=getNearestEntity($wizards[$i], $snaffles);//自分に一番近いsnaffleを取得
		$nearestBludger=getNearestEntity($wizards[$i], $bludgers);//一番近いbludger
		$nearestOpponent=getNearestEntity($wizards[$i], $opponents);//一番近いopponent
		$myGoalNearestSnaffle=getNearestEntity(Wizard::$myGoal, $snaffles);//自ゴールに一番近いSnaffleを取得
		$myGoalNearestWizard=getNearestEntity(Wizard::$myGoal, $wizards);//自ゴールに一番近いSnaffleを取得
		$myGoalNearestSnaffleNearestOpponent=getNearestEntity($myGoalNearestSnaffle, $opponents);//自ゴールに一番近いSnaffleに一番近いopponent取得
		$myGoalNearestOpponent=getNearestEntity(Wizard::$myGoal, $opponents);//自ゴールに一番近いopponentを取得
		$opponentNearestSnaffle=getNearestEntity($myGoalNearestOpponent, $snaffles);//自分に一番近いopponentに一番近いsnaffle
		$partnerNearestSnaffle=getNearestEntity($wizards[$i xor 1], $snaffles);//partnerに一番近いsnaffle
		$otherOpponent=getFarestEntity(Wizard::$myGoal, $opponents);//自ゴールに一番近い相手Entityを取得
		$otherOpponentNearestSnaffle=getNearestEntity($otherOpponent, $snaffles);//otheropponentに一番近いsnaffle
		if ($game->getTime()<9) {
			if ($wizards[$i]->getState()) {
				$wizards[$i]->throwToOpponentGoal(500);//シュート
			}
			elseif (distance($nearestBludger, $wizards[$i]) < 4000 and distance($wizards[$i], $nearestBludger) < distance($wizards[$i xor 1], $nearestBludger)) {
				$wizards[$i]->moveToOpposite($wizards[$i xor 1]);
			} else {
				$mySideNearestSnaffle = getNearestEntity($wizards[$i], $snaffles, $wizards[$i]->getMySide());
				$wizards[$i]->moveTo($mySideNearestSnaffle);
			}
		} else {
			$needed = abs($myGoalNearestSnaffle->getVelocity()[0]/15)+5; //まもるのに必要な魔力
error_log(var_export($needed, true));
error_log(var_export($myGoalNearestSnaffle->getVelocity()[0]*$reverse, true));
			if ($wizards[$i]->getState()) {
				if (distance($wizards[$i], Wizard::$opponentGoal) < 5000) {
					$wizards[$i]->throwTo(Wizard::$opponentGoal);
				}/* elseif(distance($wizards[$i], $nearestOpponent)>3000 and $wizards[$i]->isFront($wizards[$i xor 1],1000) and distance($wizards[$i], $wizards[$i xor 1])) {//パス
					$offset = $wizards[$i xor 1]->getVelocity();
					$wizards[$i]->throwTo($wizards[$i xor 1], 500, $offset);
					}*/ else {
					$wizards[$i]->throwToOpponentGoal(500);//シュート
				}
			} /*elseif(distance($nearestSnaffle, $wizards[$i]) > 1500 and $partnerCastSpellToTarget === NULL and $wizards[$i]->isBehind($nearestSnaffle, 1000) and intval(distance($nearestSnaffle, $wizards[$i])/100 + 5) < $myMagic) {//全てのsnaffleでforeachしたい//まえにもってくるやつ
				$target = new Entity($wizards[$i]->getPosition()[0]+1000, $wizards[$i]->getPosition()[1]);
				$wizards[$i]->castSpellTo($nearestSnaffle, $target, min(intval(distance($nearestSnaffle, $wizards[$i])/100 + 5),$myMagic));
				$partnerCastSpellToTarget=$target;
				}*/ elseif($partnerCastSpellToSave===NULL and distance($myGoalNearestSnaffle, Wizard::$myGoal) < 2500 and $myGoalNearestSnaffle->getVelocity()[0]*$reverse<0 and $myMagic >= $needed/* and $myMagic > $needed*/) {//まもるやつ
				/*
				if ($myGoalNearestSnaffle->getPosition()[1]<3750) $inv = -1; else $inv = 1;
				$target = new Entity($myGoalNearestOpponent->getPosition()[0], $myGoalNearestOpponent->getPosition()[1]+($inv*1500));
				$wizards[$i]->castSpellTo($myGoalNearestSnaffle, $target, min(50,$myMagic));
				 */
				$obstacles = $opponents;
				$obstacles += $bludgers;
				$results = [];
				foreach ($obstacles as $obstacle) {
					if ($myGoalNearestSnaffle->isFront($obstacle, 1000) and distance($myGoalNearestSnaffle, $obstacle)<3000) {
						array_push($results, $obstacle);
					}
				}
				$results = getEntityBetweenY($results, [$myGoalNearestSnaffle->getPosition()[1]-500, $myGoalNearestSnaffle->getPosition()[1]+500]);
				if (count($results) > 0) {
					$neaestObstacle = getNearestEntity($myGoalNearestSnaffle, $results);
					$offset = intval(distance($myGoalNearestSnaffle, $neaestObstacle));
					if ($myGoalNearestSnaffle->getPosition()[1]<3750) $offset*=-1;
					$target = new Entity($neaestObstacle->getPosition()[0], $neaestObstacle->getPosition()[1]+$offset);
				} else {
					$target = Wizard::$opponentGoal;
				}
				if ($myMagic > $needed)
					$wizards[$i]->castSpellTo($myGoalNearestSnaffle, $target, $needed);
				else
					$wizards[$i]->castSpellTo($myGoalNearestSnaffle, $target, $myMagic);
					//$wizards[$i]->castSpellTo($myGoalNearestSnaffle, Wizard::$opponentGoal, min($needed,$myMagic));
				$partnerCastSpellToSave = $myGoalNearestSnaffle;
			} elseif($partnerCastSpellToFinish===NULL and distance(Wizard::$opponentGoal, $opponentGoalNearestSnaffle)+50<distance(Wizard::$opponentGoal, $opponentGoalNearestOpponent) and intval(distance($opponentGoalNearestSnaffle, Wizard::$opponentGoal)/100+5) < $myMagic) {//とどめ
				$wizards[$i]->castSpellTo($opponentGoalNearestSnaffle, Wizard::$opponentGoal, min(intval(abs(distance($opponentGoalNearestSnaffle, Wizard::$opponentGoal)/100+5-intval(abs($opponentGoalNearestSnaffle->getVelocity()[0]/100)))),$myMagic));
				$partnerCastSpellToFinish=$opponentGoalNearestSnaffle->getId();
			} else {
				//数で負けていたら、後ろにいるwizardが後ろで一番近い　snaffleへ
				$behindSnaffles = getEntityBetweenX($snaffles, [$wizards[$i]->getPosition()[0], Wizard::$myGoal->getPosition()[0]]);
				if ($partnerBlocking===NULL and count($behindSnaffles)>0 and $wizards[$i]->getId() == getNearestEntity(Wizard::$myGoal, $wizards)->getId() and $wizards[$i]->isLose()) {
					$wizards[$i]->moveToNearestEntity($behindSnaffles);
					/*
					$nearestSnaffle = $wizards[$i]->getNearestSnaffle($behindSnaffles);
					$wizards[$i]->keepSocialDistanceFrom($nearestSnaffle);
					 */
					$partnerBlocking = $nearestSnaffle;
				} else {
					$wizards[$i]->moveToNearestSnaffle($partnerMoveToTarget);
					$partnerMoveToTarget = $nearestSnaffle;
				}
			}
			/*
			$wizards[$i]->keepSocialDistanceFrom($nearestOpponent);//相手とのあいだに入って守る
			$wizards[$i]->castSpellTo($myGoalNearestSnaffle, $wizards[$i], min(intval(distance(Wizard::$myGoal, $wizards[$i])/100),$myMagic));
			$wizards[$i]->castSpellTo($opponentGoalNearestSnaffle, Wizard::$opponentGoal, min(intval(distance(Wizard::$opponentGoal, $opponentGoalNearestSnaffle)/100+5),$myMagic));
			 */
		}	
	}
	$game->incTime();
	if ($ignoreFlag>0)$ignoreFlag--;
}//error_log(var_export('b', true));
?>


