<?php
#アマチュア無線技師試験問題対策
/*
 * 通常の試験対策とモールス信号の練習の２つある
 * 得点状況はデータベースに記録される
*/
require __DIR__ .'/../conf/conf.php';
require __DIR__ .'/../conf/users.php';
require __DIR__ .'/../conf/db.php';

const ABC = ['1','2','3','4','5','6','7','8','9','0','Q','A','Z','X','S','W','E','D','C','V','F','R','T','G','B','N','H','Y','U','J','M','K','I','O','L','P'];
const MORS_MAP = ['-----','.----','..---','...--','....-','.....','-....','--...','---..','----.',
'.-','-...','-.-.','-..','.','..-.','--.','....','..','.---','-.-','.-..','--','-.',
'---','.--.','--.-','.-.','...','-','..-','...-','.--','-..-','-.--','--..',
'.-.-.-','--..--','..--..','-....-','-..-.','.--.-.','---...','.----.','-.--.','-.--.-','-...-','.-.-.','.-..-.','-..-',
'...---...','........','-...-',
'.-.-','..-..','---.','.-..-','..--','.-...','----','-.---','.-.--','--.--','-.-.-','-.-..','-..--','-...-','..-.-','--.-.','.--..','--..-','-..-.','.---.','---.-','.-.-.','..--.','.--.-','.-.-..'];
const CHAR_MAP = ['0','1','2','3','4','5','6','7','8','9',
'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
'.',',','?','-','/','@',':',"'",'(',')','=','+','"','*',
'SOS','HH','BT',
'ろ','と','そ','ヰ','の','お','こ','え','て','あ','さ','き','ゆ','め','み','し','ヱ','ひ','も','せ','す','ん','゜','ー','。'];
const SAME_KANA = ['、','る','も','ん','い','は','に','ほ','へ','ち','り','ぬ','を','わ','か','よ','た','れ','つ','ね','な','ら','む','う','く','や','ま','け','ふ','゛'];
const SAME_ALPH = ['.','(','/','+','A', 'B', 'C', 'D','E','F','G', 'H', 'J', 'K', 'L', 'M','N', 'O','P','Q', 'R', 'S','T','U', 'V', 'W', 'X', 'Y', 'Z', 'I'];
const KANA_DOTG = ['が','ぎ','ぐ','げ','ご','ざ','じ','ず','ぜ','ぞ','だ','ぢ','づ','で','ど','ば','び','ぶ','べ','ぼ'];
const NOKANA_DOTG = ['か','き','く','け','こ','さ','し','す','せ','そ','た','ち','つ','て','と','は','ひ','ふ','へ','ほ'];
const KANA_DOTP = ['ぱ','ぴ','ぷ','ぺ','ぽ'];
const NOKANA_DOTP = ['は','ひ','ふ','へ','ほ'];

class Wave{
	public int $ch;
	public int $bits;
	public int $rate;
	public string $data;
	public function __construct(int $nchannel=1, int $sample_bits=16, int $sample_rate=44100){
		$this->ch = $nchannel;
		$this->rate = $sample_rate;
		$this->bits = $sample_bits;
		$this->data = '';
	}
	public function add_wave_type(string $type='sin', int $hz=440, float $di=1.0):void{
		switch ($type){
			case 'sin':
			$theta_delta = $hz * 2 * M_PI / $this->rate;
			for ($theta = 0, $i = 0; $i < $this->rate * $di; ++$i){
				$v = 0x4000 * sin($theta);
				$this->data .= pack('v', $v);
				$theta += $theta_delta;
			}
			break;
			default:
			for ($i = 0; $i < $this->rate * $di; ++$i){
				$this->data .= pack('v', 0);
			}
			break;
		}
	}
	public function add_data(string $data):void{
		$this->data .= $data;
	}
	public function make_data(string $data):string{
		$this->data = $data;
		$data_chunk = 'data'.pack('V', strlen($data)).$data;
		$block = $this->ch *($this->bits /8);
		$bysec = $block *$this->rate;
		$id = 1;
		$fchunk = 'WAVEfmt ';
		$fchunk .= pack('V', 16);
		$fchunk .= pack('v', $id);
		$fchunk .= pack('vVV', $this->ch, $this->rate, $bysec);
		$fchunk .= pack('vv', $block, $this->bits);
		$len = strlen($fchunk) + strlen($data_chunk);
		return 'RIFF'.pack('V', $len).$fchunk.$data_chunk;
	}
}

#濁音分離
function sep_kana_dot(string $kana):string{
	for ($i = 0; $i < count(KANA_DOTG); ++$i){
		$kana = str_replace(KANA_DOTG[$i], NOKANA_DOTG[$i].'゛', $kana);
	}
	for ($i = 0; $i < count(KANA_DOTP); ++$i){
		$kana = str_replace(KANA_DOTP[$i], NOKANA_DOTP[$i].'゜', $kana);
	}
	return $kana;
}
function mors_encode(string $str, bool $multibyte = false):string{
	$result = '';
	$list = strtoupper(sep_kana_dot(mb_convert_kana($str, 'Hc')));
	if (preg_match('/[あ-ん]/', $list) === 1){
		for ($i = 0; $i < count(SAME_ALPH); ++$i){
			$list = str_replace(SAME_KANA[$i], SAME_ALPH[$i], $list);
		}
	}
	$list = mb_str_split($list);
	$len = count($list);
	for ($i = 0; $i < $len; ++$i){
		$c = $list[$i];
		if ($c === "\n"){
			$result .= "\n";
			continue;
		}
		$p = array_search($c, CHAR_MAP, true);
		if ($p === false){
			$result .= $c;
			continue;
		} else {
			if ($multibyte){
				$result .= str_replace('-', 'ー', str_replace('.', '・', MORS_MAP[$p]));
			} else {
				$result .= MORS_MAP[$p];
			}
		}
		if ((($i < $len -1)&&($list[$i +1] === "\n")) || ($i === $len -1)){
			continue;
		}
		if ($multibyte){
			$result .= '　';
		} else {
			$result .= ' ';
		}
	}
	return $result;
}
function mors_decode(string $str):string{
	$result = '';
	foreach (['　'=>' ','ー'=>'-','・'=>'.','ー'=>'-','－'=>'-','･'=>'.','ｰ'=>'-'] as $k => $v){
		$str = str_replace($k, $v, $str);
	}
	$list = mb_str_split($str.' ');
	$len = count($list);
	$token = '';
	for ($i = 0; $i < $len; ++$i){
		$c = $list[$i];
		if (($c === '.') || ($c === '-')){
			$token .= $c;
		} elseif ($token !== ''){
			$p = array_search($token, MORS_MAP, true);
			if ($p === false){
				$result .= $token;
			} else {
				$result .= CHAR_MAP[$p];
			}
			if ($c === "\n"){
				$result .= "\n";
			} elseif ($c !== ' '){
				$result .= $c;
			}
			$token = '';
		} else {
			$result .= $c;
			$token = '';
		}
	}
	if (preg_match('/[あ-ん]/', $result) === 1){
		for ($i = 0; $i < count(SAME_ALPH); ++$i){
			$result = str_replace(SAME_ALPH[$i], SAME_KANA[$i], $result);
		}
	}
	return $result;
}
function create_wav_from_mors(string $mors, float $hz=880, float $speed=10):string{
	if ($speed <= 0){
		$speed = 10;
	}
	if ($hz <= 0){
		$hz = 880;
	}
	$wav = new Wave();
	$c1 = 1/$speed;
	foreach (['　'=>' ','ー'=>'-','・'=>'.','ー'=>'-','－'=>'-', "\n"=>'  '] as $k => $v){
		$mors = str_replace($k, $v, $mors);
	}
	$list = mb_str_split($mors);
	$len = count($list);
	for ($i = 0; $i < $len; ++$i){
		$c = $list[$i];
		if ($c === '.'){
			$wav->add_wave_type('sin', $hz, $c1);
		} else if ($c === '-'){
			$wav->add_wave_type('sin', $hz, $c1 *3);
		} else if (($i < $len -1) && ($c === ' ') && ($list[$i +1] === ' ')){
			$wav->add_wave_type('none', $hz, $c1 *7);
			$i += 1;
		} else {
			$wav->add_wave_type('none', $hz, $c1 *3);
		}
		if (($i < $len -1) && (($list[$i +1] === '.') || ($list[$i +1] === '-'))){
			$wav->add_wave_type('none', $hz, $c1);
		}
	}
	return $wav->make_data($wav->data);
}

#適当に文字を生成
function abcrand(int $min, int $max, bool $kana=false):string{
	$len = random_int($min, $max);
	$alen = count(ABC) -1;
	$r = [];
	for ($i = 0; $i < $len; ++$i){
		$r[] = ABC[random_int(0, $alen)];
	}
	return implode('', $r);
}

function main():int{
	$conf = new GakuUra();
	$user = new GakuUraUser($conf);
	$login_data = $user->login_check();
	$practice_dir = $conf->data_dir.'/practice/problem';
	$mode = in_array($_GET['Mode']??'',['normal','morse'],true)?$_GET['Mode']:'index';
	$html = $mode;
	$replace = ['PROBLEM_LIST'=>''];
	if (list_isset($_POST,['submit','session_token']) && $conf->check_csrf_token('practice_'.$_POST['submit'],$_POST['session_token'],true)){
		$submit = $_POST['submit'];
		if ($submit==='normal' && list_isset($_POST,['problemA','problemB'])){
			$g = new GakuUraSQL('sqlite', $practice_dir.'/problem.db');
			if (!$g->table_exists($_POST['problemA']) || !$g->table_exists($_POST['problemB'])){
				$conf->form_die();
			}
			$pointA = 0;
			$pointB = 0;
			$problemA = $g->get_rows($_POST['problemA']);
			$problemB = $g->get_rows($_POST['problemB']);
			#法規16問
			$pre_id = 16;
			for ($i = 1;$i <= $pre_id;++$i){
				$ans = $_POST['ansA'.$i]??'';
				$r = $problemA[$i-1];
				$replace['PROBLEM_LIST'] .= '<dl><dt>問題 '.$i.'</dt><dd>'.$r['question'].'</dd>';
				if ($r['img']!=='none' && is_file($practice_dir.'/img/'.$r['img'])){
					$im = $practice_dir.'/img/'.$r['img'];
					$sz = getimagesize($im);
					$replace['PROBLEM_LIST'] .= '<dd><img src="data:'.mime_content_type($im).';base64,'.base64_encode(file_get_contents($im)).'" width="'.$sz[0].'px" height="'.$sz[1].'px"></dd>';
				}
				$replace['PROBLEM_LIST'] .= '<dd>正解: '.$r['good'].'</dd>';
				if ($ans === ''){
					$replace['PROBLEM_LIST'] .= '<dd><span class="false">未回答</span></dd>';
				} else {
					if ($ans === pass($r['good'])){
						++$pointA;
						$replace['PROBLEM_LIST'] .= '<dd><span class="true">正解</span></dd>';
					} else {
						$wa = '';
						for ($j=1;$j <= 3;++$j){
							if ($ans === pass($r['bad'.$j])){
								$wa=$r['bad'.$j];
								break;
							}
						}
						$replace['PROBLEM_LIST'] .= '<dd><span class="false">不正解</span> (貴方の回答:'.$wa.')</dd>';
					}
				}
				$replace['PROBLEM_LIST'] .= '<dd>解説: '.($r['description']??'').'</dd></dl>';
			}
			#工学14問
			for ($i = 1;$i <= 14;++$i){
				$ans = $_POST['ansB'.$i]??'';
				$r = $problemB[$i-1];
				$replace['PROBLEM_LIST'] .= '<dl><dt>問題 '.$i+$pre_id.'</dt><dd>'.$r['question'].'</dd>';
				if ($r['img']!=='none' && is_file($practice_dir.'/img/'.$r['img'])){
					$im = $practice_dir.'/img/'.$r['img'];
					$sz = getimagesize($im);
					$replace['PROBLEM_LIST'] .= '<dd><img src="data:'.mime_content_type($im).';base64,'.base64_encode(file_get_contents($im)).'" width="'.$sz[0].'px" height="'.$sz[1].'px"></dd>';
				}
				$replace['PROBLEM_LIST'] .= '<dd>正解: '.$r['good'].'</dd>';
				if ($ans === ''){
					$replace['PROBLEM_LIST'] .= '<dd><span class="false">未回答</span></dd>';
				} else {
					if ($ans === pass($r['good'])){
						++$pointB;
						$replace['PROBLEM_LIST'] .= '<dd><span class="true">正解</span></dd>';
					} else {
						$wa = '';
						for ($j=1;$j <= 3;++$j){
							if ($ans === pass($r['bad'.$j])){
								$wa=$r['bad'.$j];
								break;
							}
						}
						$replace['PROBLEM_LIST'] .= '<dd><span class="false">不正解</span> (貴方の回答:'.$wa.')</dd>';
					}
				}
				$replace['PROBLEM_LIST'] .= '<dd>解説: '.($r['description']??'').'</dd></dl>';
			}
			$replace['PROBLEM_LIST'] = '<p>得点 法規:'.$pointA *5 .'/'. 16*5 .'点, 工学:'.$pointB *5 .'/'. 14*5 .'点</p>'.$replace['PROBLEM_LIST'];
			$replace['NEXT_LINK'] = '<a href="./">メニューに戻る</a>　<a href="?Mode=normal">もう一回やる</a>';
			$u = new GakuUraSQL('sqlite', $practice_dir.'/points.db');
			if(!$u->table_exists($mode)) $u->make_table($mode,['date'=>'TEXT NOT NULL','user_id'=>'INTEGER NOT NULL DEFAULT 0','name'=>'TEXT NOT NULL DEFAULT 不明','pointA'=>'INTEGER NOT NULL','pointB'=>'INTEGER NOT NULL']);
			if ($login_data['result']){
				$user_data = $login_data['user_data'];
			} else {
				$user_data = ['id'=>0,'name'=>get_ip()];
			}
			$u->append_row($mode, ['date'=>date('Y/m/d H:i'),'user_id'=>$user_data['id'],'name'=>$user_data['name'],'pointA'=>$pointA*5,'pointB'=>$pointB*5]);
		} elseif ($submit === 'morse'){
			for ($i = 0; $i < 10; ++$i){
				if (!isset($_POST['prb'.$i], $_POST['ans'.$i])){
					$conf->form_die();
				}
				for ($i = 0, $point = 0; $i < 10; ++$i){
					$p = str_replace('.', '・', str_replace('-', 'ー', str_replace(' ', '　', h($_POST['prb'.$i]))));
					$a = mors_decode($p);
					$replace['PROBLEM_LIST'] .= '<p>('.$i +1 .')'.$p.'<audio preload="auto" loading="auto" decoding="async" src="data:audio/wav;base64,'.base64_encode(create_wav_from_mors($p)).'" controls></audio></p><p>解答 '.$a.'</p>';
					if ($a === strtoupper($_POST['ans'.$i])){
						++$point;
						$replace['PROBLEM_LIST'] .= '<p><span class="true">正解</span></p><p><br></p>';
					} else {
						$replace['PROBLEM_LIST'] .= '<p><span class="false">不正解</span>(貴方の解答: '.h($_POST['ans'.$i]).')</p><p><br></p>';
					}
				}
				$replace['PROBLEM_LIST'] = '<p>得点: '.$point*10 .'点</p>'.$replace['PROBLEM_LIST'];
				$replace['NEXT_LINK'] = '<a href="./">メニューに戻る</a>　<a href="?Mode=morse">もう一回やる</a>';
			}
		}
		$u = new GakuUraSQL('sqlite', $practice_dir.'/points.db');
		if(!$u->table_exists($mode)) $u->make_table($mode,['date'=>'TEXT NOT NULL','user_id'=>'INTEGER NOT NULL DEFAULT 0','name'=>'TEXT NOT NULL DEFAULT 不明','point'=>'INTEGER NOT NULL']);
		if ($login_data['result']){
			$user_data = $login_data['user_data'];
		} else {
			$user_data = ['id'=>0,'name'=>get_ip()];
		}
		$u->append_row($mode, ['date'=>date('Y/m/d H:i'),'user_id'=>$user_data['id'],'name'=>$user_data['name'],'point'=>$point*10]);
	} elseif ($mode == 'normal'){
		$problemA_table = '';
		$problemA = '';
		$problemB_table = '';
		$problemB = '';
		$g = new GakuUraSQL('sqlite', $practice_dir.'/problem.db');
		$tables = $g->get_tables();
		shuffle($tables);
		foreach ($tables as $t){
			if (str_starts_with($t, 'A_')){
				$problemA_table = $t;
				$problemA = $g->get_rows($t);
				break;
			}
		}
		foreach ($tables as $t){
			if (str_starts_with($t, 'B_')){
				$problemB_table = $t;
				$problemB = $g->get_rows($t);
				break;
			}
		}
		if (!$g->is_connect || $problemA==='' || $problemB===''){
			$replace['PROBLEM_LIST'] = '問題データを読み込めませんでした。';
		} else {
			#問題の並び順をランダムにする
			shuffle($problemA);
			shuffle($problemB);
			$id_pre = 0;
			#法規16問
			foreach ($problemA as $id=>$r){
				$id_pre = $id+1;
				$replace['PROBLEM_LIST'] .= '<dl><dt>問題 '.$id_pre .'</dt><dd>'.$r['question'].'</dd>';
				if ($r['img']!=='none' && is_file($practice_dir.'/img/'.$r['img'])){
					$im = $practice_dir.'/img/'.$r['img'];
					$sz = getimagesize($im);
					$replace['PROBLEM_LIST'] .= '<dd><img src="data:'.mime_content_type($im).';base64,'.base64_encode(file_get_contents($im)).'" width="'.$sz[0].'px" height="'.$sz[1].'px"></dd>';
				}
				$option = [$r['good'], $r['bad1'], $r['bad2'], $r['bad3']];
				shuffle($option);
				foreach($option as $i=>$o) $replace['PROBLEM_LIST'].='<dd><label><input type="radio" name="ansA'.$r['id'].'" value="'.pass($o).'">('. 1 +$i.') '.$o.'</label></dd>';
				$replace['PROBLEM_LIST'] .= '</dl>';
			}
			#工学14問
			foreach ($problemB as $id=>$r){
				$replace['PROBLEM_LIST'] .= '<dl><dt>問題 '.$id_pre+$id+1 .'</dt><dd>'.$r['question'].'</dd>';
				if ($r['img']!=='none' && is_file($practice_dir.'/img/'.$r['img'])){
					$im = $practice_dir.'/img/'.$r['img'];
					$sz = getimagesize($im);
					$replace['PROBLEM_LIST'] .= '<dd><img src="data:'.mime_content_type($im).';base64,'.base64_encode(file_get_contents($im)).'" width="'.$sz[0].'px" height="'.$sz[1].'px"></dd>';
				}
				$option = [$r['good'], $r['bad1'], $r['bad2'], $r['bad3']];
				shuffle($option);
				foreach($option as $i=>$o) $replace['PROBLEM_LIST'].='<dd><label><input type="radio" name="ansB'.$r['id'].'" value="'.pass($o).'">('. 1 +$i.') '.$o.'</label></dd>';
				$replace['PROBLEM_LIST'] .= '</dl>';
			}
			$replace['SESSION_TOKEN'] = $conf->set_csrf_token('practice_'.$mode);
			$replace['PROBLEM_LIST'] .= '<input type="hidden" name="problemA" value="'.$problemA_table.'">';
			$replace['PROBLEM_LIST'] .= '<input type="hidden" name="problemB" value="'.$problemB_table.'">';
			$replace['NEXT_LINK'] = '<label><button type="submit" name="submit" value="normal">採点する！</button></label>';
		}
	} elseif ($mode === 'morse'){
		for ($i = 0; $i < 10; ++$i){
			$abc = abcrand(3, 5);
			$replace['PROBLEM_LIST'] .= '<p>('.$i +1 .')'.mors_encode($abc, true).'<audio preload="auto" loading="auto" decoding="async" src="data:audio/wav;base64,'.base64_encode(create_wav_from_mors(mors_encode($abc))).'" controls></audio><input type="hidden" name="prb'.$i.'" value="'.mors_encode($abc).'"></p><p><label>回答<input type="text" name="ans'.$i.'"></label></p><p><br></p>';
		}
		$replace['SESSION_TOKEN'] = $conf->set_csrf_token('practice_'.$mode);
		$replace['NEXT_LINK'] = '<label><button type="submit" name="submit" value="morse">採点する！</button></label>';
	}
	$conf->content_type('text/html');
	$conf->htmlf('practice', $html, $replace, true);
	return 0;
}
