<?php
declare (strict_types = 1);
// the program may be represented in many systems, given the same rules (HTML,canvas,altro),
// the interfaces define what the system expects of the components for the collaboration
interface IGrid {
    public function render(): string;
    public function clear(): void;
    // update the underlying data structure and render it
    public function generateAndRender(): string;
}
interface ICell {
    public function render(): string;
    public function isAlive(): bool;
    public function setAlive(bool $will_live): void;
    public function willLive(int $c_alive_near): bool;
}
//----------------------------------------------------------------------------
//  Base implementation
//----------------------------------------------------------------------------
abstract class CellBase implements ICell {
    protected bool $is_alive = false;
    public function __construct(bool $is_alive) {
        $this->is_alive = $is_alive;
    }
    public function isAlive(): bool {
        return $this->is_alive;
    }
    public function setAlive(bool $will_live): void{
        $this->is_alive = $will_live;
    }
    // logica di verifica della vitalità della cella, comune a tutti i tipi di cella
    // Le transizioni dipendono unicamente dallo stato delle celle vicine in quella generazione:
    // - Qualsiasi cella viva con meno di due celle vive adiacenti muore, come per effetto d'isolamento;
    // - Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
    // - Qualsiasi cella viva con più di tre celle vive adiacenti muore, come per effetto di sovrappopolazione;
    // - Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.
    public function willLive(int $c_alive_near): bool{
        // - Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
        $healthy = $this->isAlive() && in_array($c_alive_near, [2, 3]);
        // - Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.
        $reproduction = !$this->isAlive() && $c_alive_near === 3;
        if ($healthy || $reproduction) {
            return true;
        }
        // death is the most obvious outcome
        return false;
    }
    // algoritmo didascalico, come dettato dei requirements, volendo si può semplificare
    // if ($this->isAlive()) {
    //     // - Qualsiasi cella viva con meno di due celle vive adiacenti muore, come per effetto d'isolamento;
    //     if ($c_alive_near < 2) {
    //         return false;
    //     } elseif (in_array($c_alive_near, [2, 3])) {
    //         // - Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
    //         return true;
    //     } else {
    //         // - Qualsiasi cella viva con più di tre celle vive adiacenti muore, come per effetto di sovrappopolazione;
    //         return false;
    //     }
    // } else {
    //     // - Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.
    //     if ($c_alive_near === 3) {
    //         return true;
    //     }
    //     return false;
    // }
}
// la griglia rappresenta la matrice di celle
// logica comune a tutte le sottoclassi
abstract class BaseGrid implements IGrid {
    protected int $c_horizontal;
    protected int $c_vertical;
    // stato corrente
    // stato futuro calcolato da generate()
    /** @var  Matrix $matrix */
    protected $matrix;
    public function __construct(
        int $c_horizontal,
        int $c_vertical,
        Matrix $matrix
    ) {
        $this->c_horizontal = $c_horizontal;
        $this->c_vertical = $c_vertical;
        $this->matrix = $matrix;
    }
    // computa la prossima generazione, il nuovo stato e aggiorna la propria matrice con la nuova
    protected function generateNext(): void{
        $matrix_new = $this->matrix->generateNext(
            $this->c_horizontal,
            $this->c_vertical
        );
        $this->matrix = $matrix_new;
    }
    // computa la prossima generazione e lo rende
    // questo metodo, assicurare che la prossima iterazione sia già calcolata
    public function generateAndRender(): string{
        $this->generateNext();
        return $this->render();
    }
}
//----------------------------------------------------------------------------
//  CLI implementation
//----------------------------------------------------------------------------
// represent the cell in CLI
class CLICell extends CellBase implements ICell {
    const CHAR_ALIVE = '#';
    const CHAR_DEAD = '.';
    public function __construct(bool $is_alive) {
        parent::__construct($is_alive);
    }
    // represent the cell as a char
    public function render(): string {
        return $this->is_alive ? self::CHAR_ALIVE : self::CHAR_DEAD;
    }
}
// represent a grid (composed of Cells) in CLI
class CLIGrid extends BaseGrid implements IGrid {
    // render in CLI the game state
    public function render(): string {
        $res = $this->matrix->dumpState(
            $row_sep = "\n",
            function ($cell): string {
                return $cell->render();
            }
        );
        return $res;
    }
    // clearing the grid between generations depends on the rendering type
    public function clear(): void{
        system('clear');
    }
}
//----------------------------------------------------------------------------
//  Matrix DS
//----------------------------------------------------------------------------
// main datastructure of the game
// - compute ther number of adjacent cells
// - compute the next generation
class Matrix {
    /** @var  ICell[][] $matrix_next */
    protected array $matrix = [];
    protected string $cell_type;
    //
    public function __construct(string $cell_type) {
        // param validation:
        /** @psalm-suppress ArgumentTypeCoercion */
        if (!is_subclass_of($cell_type, 'ICell', $allow_str = true)) {
            $msg = sprintf('Errore: cell type is undefined "%s" or of wrong type', $cell_type);
            throw new \Exception($msg);
        }
        $this->cell_type = $cell_type;
    }
    /**
     * access the underlying data structure
     * @return null|ICell
     */
    protected function at(int $x, int $y) {
        return isset($this->matrix[$x][$y]) ? $this->matrix[$x][$y] : null;
    }
    /**
     * apply the closure to each element
     * @param callable(ICell, int, int): ICell $fn
     */
    public function map($fn): void {
        foreach ($this->matrix as $x => $row) {
            foreach ($row as $y => &$cell) {
                $cell = $fn($cell, (int)$x, (int)$y);
            }
        }
    }
    // count the adjacent cells tha are alive
    public function getNearAliveCount(int $x, int $y): int{
        /** @var  array<int, null|ICell> $near_cells */
        $near_cells = [];
        // brute force procedural aproach, which is super clear
        // up left
        $near_cells[] = $this->at($x - 1, $y - 1);
        // directly above
        $near_cells[] = $this->at($x - 1, $y);
        // up right
        $near_cells[] = $this->at($x - 1, $y + 1);
        // left
        $near_cells[] = $this->at($x, $y - 1);
        // right
        $near_cells[] = $this->at($x, $y + 1);
        // bottom left
        $near_cells[] = $this->at($x + 1, $y - 1);
        // directly below
        $near_cells[] = $this->at($x + 1, $y);
        // bottom right
        $near_cells[] = $this->at($x + 1, $y + 1);
        // mantieni solo le celle vive
        $near_cells_alive = array_filter($near_cells,
            fn($cell) => !empty($cell) && $cell->isAlive() // true retained, false skipped
        );
        $c_alive_near = count($near_cells_alive);
        return $c_alive_near;
    }
    // initialize matrix state
    // by providing a $max=1 50% of the grid will be populated, incrising the number the grid will be less populated
    public function initState(int $c_horizontal, int $c_vertical, int $max = 1): void{
        // populate the matrix
        $this->matrix = [];
        for ($x = 0; $x < $c_horizontal; $x++) {
            for ($y = 0; $y < $c_vertical; $y++) {
                $rnd_is_alive = (random_int($min = 0, $max) === 1);
                /** @var ICell $cell */
                $cell = new $this->cell_type($rnd_is_alive);
                $this->matrix[$x][$y] = $cell;
            }
        }
    }
    /** @param bool[][]|int[][] $state */
    public function setState(array $state = []): void {
        // init cells as instructed
        foreach ($state as $x => $row) {
            foreach ($row as $y => $val) {
                /** @var ICell $cell */
                $cell = new $this->cell_type((bool) $val);
                // $cell->x = $x; //dbg info
                // $cell->y = $y;
                $this->matrix[$x][$y] = $cell;
            }
        }
    }
    // compute the next generation
    public function generateNext(int $c_horizontal, int $c_vertical): Matrix{
        $matrix_next = [];
        for ($x = 0; $x < $c_horizontal; $x++) {
            for ($y = 0; $y < $c_vertical; $y++) {
                $c_alive_near = $this->getNearAliveCount($x, $y);
                $cell_prev = $this->at($x, $y);
                if (!empty($cell_prev)) {
                    $will_live = $cell_prev->willLive($c_alive_near);
                    $matrix_next[$x][$y] = $will_live;
                } else {
                    $msg = sprintf('Error: cant find a cell at [%s,%s]', $x, $y);
                    throw new \Exception($msg);
                }
            }
        }
        $matrix = new Matrix($this->cell_type);
        $matrix->setState($matrix_next);
        return $matrix;
    }
    /**
     * @param callable(ICell): string $fn
     */
    public function dumpState(string $row_sep = "\n", callable $fn = null): string {
        // dumper function di default
        if (empty($fn)) {
            /** @param callable(ICell): string $fn */
            $fn = function (ICell $cell): string {
                return $cell->isAlive() ? '1' : '0';
            };
        }
        $ret = '';
        /** @var int[] $row */
        foreach ($this->matrix as $x => $row) {
            foreach ($row as $y => $val) {
                $cell = $this->matrix[$x][$y];
                $ret .= $fn($cell);
            }
            $ret .= $row_sep;
        }
        return $ret;
    }
}
// game manager: builds dependencies and initialize the object graph, the run the generations
class GameOfLife {
    private String $grid_type;
    private String $cell_type;
    //
    private int $c_horizontal;
    private int $c_vertical;
    private int $num_cicles; // number of cycles to compute
    private int $interval_secs; // interval duration
    // NOTE: inject all state and dependency from here
    // usually from a configurarion of some kind
    public function __construct(
        string $game_rendering = 'cli',
        int $c_horizontal = 10,
        int $c_vertical = 8,
        int $num_cicles = 30,
        int $interval_secs = 1
    ) {
        $this->c_horizontal = $c_horizontal;
        $this->c_vertical = $c_vertical;
        $this->num_cicles = $num_cicles;
        $this->interval_secs = $interval_secs;
        if ($game_rendering == 'cli') {
            $this->grid_type = CLIGrid::class;
            $this->cell_type = CLICell::class;
        } else {
            $msg = sprintf('Error: game rendering "%s" not available', $game_rendering);
            throw new \InvalidArgumentException($msg);
        }
    }
    // inizia la generazione
    public function run(): void{
        $matrix = new Matrix($this->cell_type);
        $matrix->initState($this->c_horizontal, $this->c_vertical);
        /** @var IGrid $grid */
        $grid = new $this->grid_type(
            $this->c_horizontal,
            $this->c_vertical,
            $matrix
        );
        for ($i = 0; $i < $this->num_cicles; $i++) {
            $grid->clear();
            // computa la prossima generazione, il nuovo stato e rendilo
            echo $grid->generateAndRender();
            echo sprintf("cycle %s of {$this->num_cicles} \n", 1 + $i);
            /** @psalm-suppress ArgumentTypeCoercion */
            sleep($this->interval_secs);
        }
    }
}
//----------------------------------------------------------------------------
//  lib functions
//----------------------------------------------------------------------------
/**
 * accede a un array associativo e restituisce un default se l'elemento presente non fosse presente
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
    /** @var callable(mixed): string $_e */
    $_e = function ($v): string {return json_encode($v);};
    echo ($res !== $exp) ?
    "ERROR: {$_e($res)} !== {$_e($exp)}  $label\n"
    : "ok {$_e($res)} === {$_e($res)}  $label\n";
}
function arg_val(string $key, string $def = ''): string {
    /** @psalm-suppress PossiblyUndefinedArrayOffset */
    foreach ($_SERVER['argv'] as $val) {
        $found = strpos($val, '--' . $key) !== false;
        if ($found) {
            $a_val = explode('=', $val);
            return $a_val[1];
        }
    }
    return $def;
}
function arg_flag(string $key): bool {
    /** @psalm-suppress PossiblyUndefinedArrayOffset */
    foreach ($_SERVER['argv'] as $val) {
        $found = strpos($val, '--' . $key) !== false; // inizia con '--' ?
        $is_val = strpos($val, '=') !== false; // se nella form --aa=bb non è flag
        if ($found && !$is_val) {
            return true;
        }
    }
    return false;
}
//----------------------------------------------------------------------------
//  main
//----------------------------------------------------------------------------
function action_usage(array $argv): void {
    echo "{$argv[0]} [run|test]
actions:
    run = run the game
        --hnum=horizontal cells
        --vnum=vertical cels
        --cicles= number of cycles
        --int= secs interval
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
    $matrix = new Matrix(CLICell::class);
    // test empty matrix
    $matrix->setState([
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
    ]);
    $c = $matrix->getNearAliveCount($x = 0, $y = 0);
    assertEquals($c, 0, 'empty matrix');
    // test punto superiore
    $matrix->setState([
        [0, 1, 0, 0, 0],
        [1, 1, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
    ]);
    $c = $matrix->getNearAliveCount($x = 0, $y = 0);
    assertEquals($c, 3, 'matrix 1');
    // full test
    $matrix->setState([
        [1, 1, 1, 0, 0],
        [1, 1, 1, 0, 0],
        [1, 1, 1, 0, 0],
        [0, 0, 0, 0, 0],
        [0, 0, 0, 0, 0],
    ]);
    // $grid->dumpState();
    $c = $matrix->getNearAliveCount($x = 1, $y = 1);
    assertEquals($c, 8, 'matrix 8');
}
function action_run(array $argv): void{
    $game = new GameOfLife(
        $render_engine = 'cli',
        $c_horizontal = (int) arg_val('hnum', '10'),
        $c_vertical = (int) arg_val('vnum', '8'),
        $num_cicles = (int) arg_val('cicles', '30'),
        $interval_secs = (int) arg_val('int', '1'),
    );
    $game->run();
}
// run the game or the unit tests
function main(int $argc, array $argv): void {
    try {
        $action = (string) maybe($argv, '1', 'run');
        switch ($action) {
        case 'run':
            action_run($argv);
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
