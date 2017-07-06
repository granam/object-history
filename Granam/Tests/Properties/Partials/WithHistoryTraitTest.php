<?php
namespace Granam\Tests\Properties\Partials;

use Granam\History\Partials\WithHistory;
use Granam\History\Partials\WithHistoryTrait;
use Granam\Tools\ValueDescriber;
use PHPUnit\Framework\TestCase;

class WithHistoryTraitTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideSomeRandomData
     * @param $randomData
     */
    public function I_can_get_history_of_an_object($randomData): void
    {
        $somethingHistorical = new SomethingHistorical('foo', 123, 456.789);
        $changes = [];
        $changes[] = [
            'changeBy' => [
                'name' => 'i can get history of an object',
                'with' => ValueDescriber::describe($randomData),
            ],
            'result' => null,
        ];
        self::assertEquals($changes, $somethingHistorical->getHistory());

        $somethingHistorical->setBar(999);
        $changes[] = [
            'changeBy' => [
                'name' => 'i can get history of an object', // external method expected
                'with' => ValueDescriber::describe($randomData),
            ],
            'result' => 999,
        ];
        self::assertEquals($changes, $somethingHistorical->getHistory());

        $somethingHistorical->setBaz(159);
        $changes[] = [
            'changeBy' => [
                'name' => 'set baz', // lastly called internal / changed object method expected
                'with' => ValueDescriber::describe(159.0),
            ],
            'result' => 159.0,
        ];
        self::assertEquals($changes, $somethingHistorical->getHistory());

        $somethingHistorical->merge($somethingHistorical);
        $adopted = array_merge($changes, $changes);
        $adopted[] = [
            'changeBy' => [
                'name' => 'merge', // lastly called internal / changed object method expected
                'with' => ValueDescriber::describe($somethingHistorical),
            ],
            'result' => 'merged',
        ];
        self::assertEquals($adopted, $somethingHistorical->getHistory());

        $somethingLessHistorical = new SomethingLessHistorical([$minorChange = ['medium' => 'vinyl']]);
        $somethingHistorical->merge($somethingLessHistorical);
        array_unshift($adopted, $minorChange); // adopted history goes first
        $adopted[] = [
            'changeBy' => [
                'name' => 'merge', // lastly called internal / changed object method expected
                'with' => ValueDescriber::describe($somethingLessHistorical),
            ],
            'result' => 'merged',
        ];
        self::assertEquals($adopted, $somethingHistorical->getHistory());
    }

    public function provideSomeRandomData(): array
    {
        return [
            [$this->getRandomData(random_int(0, 4))],
            [$this->getRandomData(random_int(0, 4))],
            [$this->getRandomData(random_int(0, 4))],
            [$this->getRandomData(random_int(0, 4))],
        ];
    }

    private function getRandomData(int $index)
    {
        switch ($index) {
            case 0:
                return [random_int(1, 10), random_bytes(50)];
            case 1:
                return random_int(1, 10);
            case 2:
                return new \stdClass();
            case 3:
                return new \DateTime();
            case 4:
                return random_bytes(20);
            default:
                throw new \LogicException();
        }
    }
}

/** inner */
class SomethingHistorical
{
    use WithHistoryTrait;

    /**
     * @var string
     */
    private $foo;
    /**
     * @var int
     */
    private $bar;
    /**
     * @var float
     */
    private $baz;

    public function __construct(string $foo, int $bar, float $baz)
    {
        $this->foo = $foo;
        $this->bar = $bar;
        $this->baz = $baz;
        $this->noticeChangeFromOutside(null);
    }

    /**
     * @return string
     */
    public function getFoo(): string
    {
        return $this->foo;
    }

    /**
     * @return int
     */
    public function getBar(): int
    {
        return $this->bar;
    }

    /**
     * @return float
     */
    public function getBaz(): float
    {
        return $this->baz;
    }

    /**
     * @param string $foo
     */
    public function setFoo(string $foo): void
    {
        $this->foo = $foo;
        $this->noticeChangeFromOutside($foo);
    }

    /**
     * @param int $bar
     */
    public function setBar(int $bar): void
    {
        $this->bar = $bar;
        $this->noticeChangeFromOutside($bar);
    }

    /**
     * @param float $baz
     */
    public function setBaz(float $baz): void
    {
        $this->baz = $baz;
        $this->noticeChangeFromInside($baz);
    }

    /**
     * @param WithHistory|WithHistoryTrait $somethingHistorical
     */
    public function merge($somethingHistorical): void
    {
        $this->adoptHistory($somethingHistorical);
        $this->noticeChangeFromInside('merged');
    }
}
/** inner */
class SomethingLessHistorical implements WithHistory
{
    /**
     * @var array
     */
    private $someHistory;

    public function __construct(array $someHistory)
    {
        $this->someHistory = $someHistory;
    }

    public function getHistory(): array
    {
        return $this->someHistory;
    }

}