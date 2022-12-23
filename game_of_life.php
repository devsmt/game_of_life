<?php
declare (strict_types = 1);
/*
Program Requirements:
la sua evoluzione è determinata dal suo *stato iniziale*,
Si svolge su una *griglia* di *celle*
(potenzialmente che si estende all'infinito in tutte le direzioni;)
Ogni cella ha 8 vicini, che sono le celle ad essa adiacenti,
includendo quelle in senso diagonale.
Ogni cella può trovarsi in due stati: viva o morta (o accesa e spenta, on e off).
Lo stato della griglia evolve in intervalli di tempo
Gli stati di tutte le celle in un dato istante sono usati per calcolare lo stato delle celle all'istante successivo.
Tutte le celle del mondo vengono quindi aggiornate simultaneamente nel passaggio da un istante a quello successivo:
passa così una *generazione*.
Le transizioni dipendono unicamente dallo stato delle celle vicine in quella generazione:
- Qualsiasi cella viva con meno di due celle vive adiacenti muore, come per effetto d'isolamento;
- Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
- Qualsiasi cella viva con più di tre celle vive adiacenti muore, come per effetto di sovrappopolazione;
- Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.
 */
// il gioco potrebbe essere reso in più sistemi(HTML,canvas,altro),
// le segenti interfacce definiscono cosa gli altri oggetti si aspettano dalla collaborazione
interface IGrid {
    public function render(): string;
    public function clear(): void;
    public function initState(array $state = []): void;
}
interface ICell {
    public function render(int $c_alive_near): string;
    public function isAlive(): bool;
}
//----------------------------------------------------------------------------
//  CLI implementation
//----------------------------------------------------------------------------
abstract class CellBase implements ICell {
    public bool $is_alive = false;
    public function __construct(bool $is_alive) {
        $this->is_alive = $is_alive;
    }
    public function isAlive(): bool {
        return $this->is_alive;
    }
    // logica di verifica della vitalità della cella, comune a tutti i tipi di cella
    // @see tests
    // Le transizioni dipendono unicamente dallo stato delle celle vicine in quella generazione:
    // - Qualsiasi cella viva con meno di due celle vive adiacenti muore, come per effetto d'isolamento;
    // - Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
    // - Qualsiasi cella viva con più di tre celle vive adiacenti muore, come per effetto di sovrappopolazione;
    // - Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.
    public function willLive(int $c_alive_near): bool {
        if ($this->isAlive()) {
            // - Qualsiasi cella viva con meno di due celle vive adiacenti muore, come per effetto d'isolamento;
            if ($c_alive_near < 2) {
                return false;
            } elseif (in_array($c_alive_near, [2, 3])) {
                // - Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
                return true;
            } else {
                // - Qualsiasi cella viva con più di tre celle vive adiacenti muore, come per effetto di sovrappopolazione;
                return false;
            }
        } else {
            // - Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.
            if ($c_alive_near === 3) {
                return true;
            }
        }
        return false;
    }
}
// rappresenta una cella in CLI
class CLICell extends CellBase implements ICell {
    const CHAR_ALIVE = '#';
    const CHAR_DEAD = '.';
    public function __construct(bool $is_alive) {
        parent::__construct($is_alive);
    }
    // rappresenta visivamente la cella
    public function render(int $c_alive_near): string{
        $this->is_alive = $this->willLive($c_alive_near);
        return $this->is_alive ? self::CHAR_ALIVE : self::CHAR_DEAD;
    }
}

// logica comune a tutte le sottoclassi
abstract class BaseGrid implements IGrid {
    protected int $c_horizontal = 0;
    protected int $c_vertical = 0;
    protected string $cell_type;
    protected array $matrix = [];
    public function __construct(
        int $c_horizontal = 10,
        int $c_vertical = 8,
        string $cell_type
    ) {
        $this->c_horizontal = $c_horizontal;
        $this->c_vertical = $c_vertical;
        // param validation:
        if (!is_subclass_of($cell_type, 'ICell', $allow_str = true)) {
            $msg = sprintf('Errore: cell type is undefined "%s" or of wrong type', $cell_type);
            throw new \Exception($msg);
        }
        $this->cell_type = $cell_type;
    }
    // conta le 4/8 celle adiacenti, vive
    // brute force procedural aproach, which is super clear
    public function getNearAliveCount(int $x, int $y): int{
        $near_cells = [];
        /**
         * accede alla matrice
         * @return null|ICell
         */
        $_at = function (int $x, int $y) {
            if (isset($this->matrix[$x][$y])) {
                return $this->matrix[$x][$y];
            } else {
                return null;
            }
        };
        // up left
        $near_cells[] = $_at($x - 1, $y - 1);
        // directly above
        $near_cells[] = $_at($x - 1, $y);
        // up right
        $near_cells[] = $_at($x - 1, $y + 1);
        // left
        $near_cells[] = $_at($x, $y - 1);
        // right
        $near_cells[] = $_at($x, $y + 1);
        // bottom left
        $near_cells[] = $_at($x + 1, $y - 1);
        // directly below
        $near_cells[] = $_at($x + 1, $y);
        // bottom right
        $near_cells[] = $_at($x + 1, $y + 1);
        // mantieni solo le celle vive
        $near_cells_alive = array_values(array_filter($near_cells,
            function ($cell) { //cell is ICell|null
                return !empty($cell) && $cell->isAlive(); // true retained, false skipped
            }));
        $c_alive_near = count($near_cells_alive);
        return $c_alive_near;
    }
    // initialize matrix state
    /** @param int[][]  $state */
    public function initState(array $state = []): void{
        // populate the matrix
        $this->matrix = [];
        if (empty($state)) {
            // if no state is provided, init a random one
            for ($x = 0; $x < $this->c_horizontal; $x++) {
                $this->matrix[$x] = [];
                for ($y = 0; $y < $this->c_vertical; $y++) {
                    $rnd_is_alive = (bool) random_int($min = 0, $max = 1);
                    $this->matrix[$x][$y] = new $this->cell_type($rnd_is_alive);
                }
            }
        } else {
            // init cells as instructed
            foreach ($state as $x => $row) {
                foreach ($row as $y => $val) {
                    $cell = new $this->cell_type((bool) $val);
                    $cell->x = $x; //dbg info
                    $cell->y = $y;
                    $this->matrix[$x][$y] = $cell;
                }
            }
        }
    }
    // debug method
    public function dumpState(): void {
        // populate the matrix
        foreach ($this->matrix as $x => $row) {
            foreach ($row as $y => $val) {
                $cell = $this->matrix[$x][$y];
                echo $cell->isAlive() ? '1' : '0';
            }
            echo "\n";
        }
    }
}
// rappresenta la griglia resa in CLI, composta di celle
class CLIGrid extends BaseGrid implements IGrid {
    // rende in cli lo stato del gioco
    public function render(): string{
        $res = '';
        for ($x = 0; $x < $this->c_horizontal; $x++) {
            for ($y = 0; $y < $this->c_vertical; $y++) {
                $cell = $this->matrix[$x][$y];
                $c_alive_near = $this->getNearAliveCount($x, $y);
                $res .= $cell->render($c_alive_near);
            }
            $res .= "\n";
        }
        return $res;
    }
    // pulisce la griglia per il successivo rendering
    public function clear(): void{
        // TODO: maybe there's a better way
        system('clear');
    }
}
// manager del gioco, costruisce le dipendenze per fornirle agli oggetti che collabolarano
class GameOfLife {
    private String $grid_type;
    private String $cell_type;
    //
    private int $c_horizontal;
    private int $c_vertical;
    private int $num_cicles; // rendiamo n cicli della dutata di 1 secondo, potenzialmente la computazione sarebbe infinita
    // TODO: inject all state and dependency from here
    // usually from a configurarion of some kind
    public function __construct(
        string $game_rendering = 'cli',
        int $c_horizontal = 10,
        int $c_vertical = 8,
        int $num_cicles = 30
    ) {
        $this->c_horizontal = $c_horizontal;
        $this->c_vertical = $c_vertical;
        $this->num_cicles = $num_cicles;
        if ($game_rendering == 'cli') {
            $this->grid_type = CLIGrid::class;
            $this->cell_type = CLICell::class;
        } else {
            $msg = sprintf('Error: game rendering "%s" not available', $game_rendering);
            throw new \InvalidArgumentException($msg);
        }
    }
    // inizia la generazione
    public function generate(): void{
        $grid = new $this->grid_type(
            $this->c_horizontal,
            $this->c_vertical,
            $this->cell_type
        );
        $grid->initState();
        for ($i = 0; $i < $this->num_cicles; $i++) {
            $grid->clear();
            echo $grid->render();
            echo sprintf("cycle %s of {$this->num_cicles} \n", 1 + $i);
            sleep($secs = 2);
        }
    }
}
//----------------------------------------------------------------------------
//  lib functions
//----------------------------------------------------------------------------
/**
 * @param string|int $key
 * @param mixed $def
 * @return mixed
 */
function maybe(array $hash, $key, $def) {
    return isset($hash[$key]) ? $hash[$key] : $def;
}
/**
 * minimalist implementation
 * @param mixed $res
 * @param mixed $exp
 */
function assertEquals($res, $exp, string $label = ''): void{
    /** @param mixed $v */
    $_e = function ($v): string {return json_encode($v);};
    echo ($res !== $exp) ?
    "ERROR: {$_e($res)} !== {$_e($exp)}  $label\n"
    : "ok {$_e($res)} === {$_e($res)}  $label\n";
}
//----------------------------------------------------------------------------
//  main
//----------------------------------------------------------------------------
function action_usage(array $argv): void {
    echo "{$argv[0]} [run|test]
actions:
    run = run the game
    test = unit test of internals
\n";
}
// run unit tests of the procedure
function action_autotest(array $argv): void{
    $alive_cell = new CLICell($alive = true);
    $r = $alive_cell->willLive($c_alive_near = 0);
    assertEquals($r, false, 'cell alive 0');
    $r = $alive_cell->willLive($c_alive_near = 1);
    assertEquals($r, false, 'cell alive 1');
    $r = $alive_cell->willLive($c_alive_near = 2);
    assertEquals($r, true, 'cell alive 2');
    $r = $alive_cell->willLive($c_alive_near = 3);
    assertEquals($r, true, 'cell alive 3');
    $r = $alive_cell->willLive($c_alive_near = 4);
    assertEquals($r, false, 'cell alive 4');
    $r = $alive_cell->willLive($c_alive_near = 5);
    assertEquals($r, false, 'cell alive 5');
    //
    $dead_cell = new CLICell($alive = false);
    $r = $dead_cell->willLive($c_alive_near = 1);
    assertEquals($r, false, 'cell alive 1');
    $r = $dead_cell->willLive($c_alive_near = 3);
    assertEquals($r, true, 'cell alive 3');

    // grid tests
    $grid = new CLIGrid(5, 5, CLICell::class);
    // test empty matrix
    $grid->initState([
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
    ]);
    $c = $grid->getNearAliveCount($x = 0, $y = 0);
    assertEquals($c, 0, 'empty matrix');
    // test punto superiore
    $grid->initState([
        [0, 1, 0, 0, 0],
        [1, 1, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
    ]);
    $c = $grid->getNearAliveCount($x = 0, $y = 0);
    assertEquals($c, 3, 'matrix 1');
    // full test
    $grid->initState([
        [1, 1, 1, 0, 0],
        [1, 1, 1, 0, 0],
        [1, 1, 1, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
    ]);
    // $grid->dumpState();
    $c = $grid->getNearAliveCount($x = 1, $y = 1);
    assertEquals($c, 8, 'matrix 8');
}
// run the game or the unit tests
function main(int $argc, array $argv): void {
    try {
        $action = maybe($argv, '1', 'run');
        switch ($action) {
        case 'run':
            $game = new GameOfLife(
                $render_engine = 'cli',
                $c_horizontal = 10,
                $c_vertical = 8
            );
            $game->generate();
            break;
        case 'test':
        case 'unit':
        case 'unittest':
        case 'autotest':
            action_autotest($argv);
            break;
        default:
            action_usage($argv);
            break;
        }
    } catch (\Exception $e) {
        $fmt = 'Exception: %s file:%s line:%s trace: %s ';
        echo $msg = sprintf($fmt,
            $e->getMessage(),
            $e->getFile(), $e->getLine(),
            $e->getTraceAsString()
        );
    }
    exit(0);
}
// entrypoint
/** @psalm-suppress PossiblyUndefinedArrayOffset  */
main($argc = $_SERVER['argc'], $argv = $_SERVER['argv']);
