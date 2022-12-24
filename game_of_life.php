<?php
declare (strict_types = 1);
/*
Program Requirements:
la sua evoluzione è determinata dal suo *stato iniziale*,
Si svolge su una *griglia* di *celle*
(potenzialmente che si estende all'infinito in tutte le direzioni;)
Ogni cella ha *8 vicini*, che sono le celle ad essa adiacenti,
includendo quelle in senso diagonale.
Ogni cella può trovarsi in *due stati*: viva o morta (o accesa e spenta, on e off).
Lo stato della griglia evolve in intervalli di tempo
Gli stati di tutte le celle in un dato istante sono usati per calcolare lo stato delle celle all'istante successivo.
Tutte le celle del mondo vengono quindi aggiornate simultaneamente nel passaggio da un istante a quello successivo:
passa così una *generazione*.
Le transizioni dipendono unicamente dallo stato delle *celle vicine in quella generazione*:
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
    public function generate(): void;
    /** @param int[][]  $state */
    public function initState(array $state = []): void;
}
interface ICell {
    public function render(): string;
    public function isAlive(): bool;
    public function setAlive(bool $will_live): void;
    public function willLive(int $c_alive_near): bool;
}
//----------------------------------------------------------------------------
//  CLI implementation
//----------------------------------------------------------------------------
abstract class CellBase implements ICell {
    protected bool $is_alive = false;
    public function __construct(bool $is_alive) {
        $this->is_alive = $is_alive;
    }
    public function isAlive(): bool {
        return $this->is_alive;
    }
    public function setAlive(bool $will_live): void {
        $this->is_alive = $will_live;
    }
    // logica di verifica della vitalità della cella, comune a tutti i tipi di cella
    // @see tests
    // Le transizioni dipendono unicamente dallo stato delle celle vicine in quella generazione:
    // - Qualsiasi cella viva con meno di due celle vive adiacenti muore, come per effetto d'isolamento;
    // - Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
    // - Qualsiasi cella viva con più di tre celle vive adiacenti muore, come per effetto di sovrappopolazione;
    // - Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.
    public function willLive(int $c_alive_near): bool{
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

        // - Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
        $healthy = $this->isAlive() && in_array($c_alive_near, [2, 3]);
        // - Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.
        $reproduction = !$this->isAlive() && $c_alive_near === 3;
        if ($healthy || $reproduction) {
            return true;
        }
        // death is the most probable outcome
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
    public function render(): string {
        return $this->is_alive ? self::CHAR_ALIVE : self::CHAR_DEAD;
    }
}
// la griglia rappresenta la matrice di celle e computa il numero di celle adiacenti
// logica comune a tutte le sottoclassi
abstract class BaseGrid implements IGrid {
    protected int $c_horizontal = 0;
    protected int $c_vertical = 0;
    protected string $cell_type = '';
    // stato corrente
    /** @var  ICell[][] $matrix */
    protected array $matrix = [];
    // stato futuro calcolato da generate()
    /** @var  ICell[][] $matrix_next */
    protected array $matrix_next = [];
    public function __construct(
        int $c_horizontal = 10,
        int $c_vertical = 8,
        string $cell_type
    ) {
        $this->c_horizontal = $c_horizontal;
        $this->c_vertical = $c_vertical;
        // param validation:
        /** @psalm-suppress ArgumentTypeCoercion */
        if (!is_subclass_of($cell_type, (string) 'ICell', $allow_str = true)) {
            $msg = sprintf('Errore: cell type is undefined "%s" or of wrong type', $cell_type);
            throw new \Exception($msg);
        }
        $this->cell_type = $cell_type;
    }
    /**
    * accede alla matrice
    * @return null|ICell
    */
    protected function at(int $x, int $y) {
        if (isset($this->matrix[$x][$y])) {
            return $this->matrix[$x][$y];
        } else {
            return null;
        }
    }
    // conta le 4/8 celle adiacenti, vive
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
    /** @param int[][]|bool[][]  $state */
    public function initState(array $state = []): void{
        // populate the matrix
        $this->matrix = [];
        if (empty($state)) {
            // if no state is provided, init a random one
            for ($x = 0; $x < $this->c_horizontal; $x++) {
                // $this->matrix[$x] = [];
                for ($y = 0; $y < $this->c_vertical; $y++) {
                    $rnd_is_alive = (bool) random_int($min = 0, $max = 1);
                    /** @var ICell $cell */
                    $cell = new $this->cell_type($rnd_is_alive);
                    $this->matrix[$x][$y] = $cell;
                }
            }
        } else {
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
    }
    // computa la prossima generazione, il nuovo stato
    public function generate(): void {
        for ($x = 0; $x < $this->c_horizontal; $x++) {
            for ($y = 0; $y < $this->c_vertical; $y++) {
                $cell = clone $this->matrix[$x][$y];
                $c_alive_near = $this->getNearAliveCount($x, $y);
                $will_live = $cell->willLive($c_alive_near);
                $cell->setAlive($will_live);
                $this->matrix_next[$x][$y] = $cell;
            }
        }
    }
    // debug method
    public function dumpState(string $row_sep="\n"): string {
        $ret = '';
        foreach ($this->matrix as $x => $row) {
            foreach ($row as $y => $val) {
                $cell = $this->matrix[$x][$y];
                $ret .= $cell->isAlive() ? '1' : '0';
            }
            $ret .= $row_sep;
        }
        return $ret;
    }
}
// rappresenta la griglia resa in CLI, composta di celle
class CLIGrid extends BaseGrid implements IGrid {
    // rende in cli lo stato del gioco
    public function render(): string{
        // TODO: assicurare che la prossima iterazione sia già calcolata
        $this->matrix = $this->matrix_next;
        $this->matrix_next = [];
        $res = '';
        for ($x = 0; $x < $this->c_horizontal; $x++) {
            for ($y = 0; $y < $this->c_vertical; $y++) {
                $cell = $this->matrix[$x][$y];
                $res .= $cell->render();
            }
            $res .= "\n";
        }
        return $res;
    }
    // pulisce la griglia per il successivo rendering
    public function clear(): void{
        system('clear'); // TODO: maybe there's a better way
    }
}
// manager del gioco, costruisce le dipendenze per fornirle agli oggetti che collabolarano
class GameOfLife {
    private String $grid_type;
    private String $cell_type;
    //
    private int $c_horizontal;
    private int $c_vertical;
    private int $interval_secs;
    private int $num_cicles; // rendiamo n cicli della dutata di 1 secondo, potenzialmente la computazione sarebbe infinita
    // NOTA: inject all state and dependency from here
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
        $grid = new $this->grid_type(
            $this->c_horizontal,
            $this->c_vertical,
            $this->cell_type
        );
        $grid->initState();
        for ($i = 0; $i < $this->num_cicles; $i++) {
            $grid->clear();
            $grid->generate(); // computa la prossima generazione, il nuovo stato
            echo $grid->render();
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
                $c_horizontal = (int) maybe($argv, '2', 10),
                $c_vertical = (int) maybe($argv, '3', 8),
                $num_cicles = (int) maybe($argv, '4', 30)
            );
            $game->run();
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
