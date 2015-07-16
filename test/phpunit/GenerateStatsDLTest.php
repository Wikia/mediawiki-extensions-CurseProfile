<?php
use CurseProfile\ProfilePage;

class StatsDLExposer extends ProfilePage {
	public function __construct(){
		$this->user_id = 12345;
	}
}

/**
 * @group CurseProfile
 * @covers CurseProfile\ProfilePage
 */
class GenerateStatsDLTest extends MediaWikiTestCase {

	protected $stats;
	protected function setUp() {
		parent::setUp();
		$this->stats = new StatsDLExposer;
	}

	public function testSimpleValues() {
		$this->assertEquals(1, $this->stats->generateStatsDL(1), 'Small numbers should be returned unchanged');
		$this->assertEquals('Hello world', $this->stats->generateStatsDL('Hello world'), 'Strings should be returned unchanged');
		$this->assertEquals('1,000', $this->stats->generateStatsDL(1000), 'Large numbers should be formatted');
	}

	public function testNull() {
		$this->assertEquals('0', $this->stats->generateStatsDL(NULL), 'Null should return zero');
	}

	public function testFlatArray() {
		$template = [
			'a' => 10,
			'b' => 2000,
			'c' => 3455,
		];
		$this->assertEquals(
			'<dl>'
				.'<dt>&lt;a&gt;</dt><dd>10</dd>'
				.'<dt>&lt;b&gt;</dt><dd>2,000</dd>'
				.'<dt>&lt;c&gt;</dt><dd>3,455</dd>'
			.'</dl>',
			$this->stats->generateStatsDL($template),
			'A flat list should generate a simple definition list'
		);
	}

	public function testNestedArrayWithRollup() {
		$template = [
			'a' => 10,
			'b' => [2000,
				'd' => 500,
				'e' => 1500,
				'f' => 'nothing'
			],
			'c' => 3455,
		];
		$this->assertEquals(
			'<dl>'
				.'<dt>&lt;a&gt;</dt><dd>10</dd>'
				.'<dt>&lt;b&gt;</dt><dd>2,000</dd>'
				.'<dl>'
					.'<dt>&lt;d&gt;</dt><dd>500</dd>'
					.'<dt>&lt;e&gt;</dt><dd>1,500</dd>'
					.'<dt>&lt;f&gt;</dt><dd>nothing</dd>'
				.'</dl>'
				.'<dt>&lt;c&gt;</dt><dd>3,455</dd>'
			.'</dl>',
			$this->stats->generateStatsDL($template),
			'A nested list with a rollup should produce a nested list with a number across from its header'
		);
	}

	public function testNestedArrayWithoutRollup() {
		$template = [
			'a' => 10,
			'b' => [
				'd' => 500,
				'e' => 1500,
				'f' => 'nothing'
			],
			'c' => 3455,
		];
		$this->assertEquals(
			'<dl>'
				.'<dt>&lt;a&gt;</dt><dd>10</dd>'
				.'<dt>&lt;b&gt;</dt>'
				.'<dl>'
					.'<dt>&lt;d&gt;</dt><dd>500</dd>'
					.'<dt>&lt;e&gt;</dt><dd>1,500</dd>'
					.'<dt>&lt;f&gt;</dt><dd>nothing</dd>'
				.'</dl>'
				.'<dt>&lt;c&gt;</dt><dd>3,455</dd>'
			.'</dl>',
			$this->stats->generateStatsDL($template),
			'A nested list with a rollup should produce a nested list with a number across from its header'
		);
	}
}
