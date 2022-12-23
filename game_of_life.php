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

USAGE:
tested on php 7.4
php game_of_life.php
 */

// il gioco potrebbe essere reso in più sistemi(HTML,canvas,altro),
// le segenti interfacce definiscono cosa gli altri oggetti si aspettano dalla collaborazione
interface IGrid {
    public function render(): string;
    public function clear(): void;
    public function initState(): void;
}
interface ICell {
    public function render(): string;
}

//----------------------------------------------------------------------------
//  CLI implementation
//----------------------------------------------------------------------------

abstract class CellBase implements ICell {
    public bool $is_alive = false;
    public function __construct(bool $is_alive = false) {
        $this->is_alive = $is_alive;
    }
    // TODO: logica di verifica della vitalità della cella, comune a tutti i tipi di cella
    // TODO: this should be testable
    // Le transizioni dipendono unicamente dallo stato delle celle vicine in quella generazione:
    // - Qualsiasi cella viva con meno di due celle vive adiacenti muore, come per effetto d'isolamento;
    // - Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
    // - Qualsiasi cella viva con più di tre celle vive adiacenti muore, come per effetto di sovrappopolazione;
    // - Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.
    protected function willLive(int $c_alive_near): bool {
        if ($this->is_alive) {
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

    public function __construct() {
        parent::__construct();
    }
    // rappresenta visivamente la cella
    public function render(int $c_alive_near = 0): string {
        return $this->willLive($c_alive_near) ? self::CHAR_ALIVE : self::CHAR_DEAD;
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
        // param validation
        $autoload = false;
        if (
            !class_exists($cell_type, $autoload)
            || !in_array('ICell', $a_impl = class_implements($cell_type, $autoload))
        ) {
            $msg = sprintf('Errore: cell type is undefined "%s" or of wrong type', $cell_type);
            throw new \Exception($msg);
        }
        $this->cell_type = $cell_type;
    }
    // conta le 4/8 celle adiacenti, vive
    public function getNearAliveCount(int $x, int $y): int{
        $c_alive_near = random_int($min = 1, $max = 8);
        return $c_alive_near;
    }
    // TODO: maybe should be declared in the interface
    // initialize matrix state
    public function initState(): void{
        // populate the matrix
        $this->matrix = [];
        for ($i = 0; $i < $this->c_horizontal; $i++) {
            $this->matrix[$i] = [];
            for ($j = 0; $j < $this->c_vertical; $j++) {
                $rnd_is_alive = (bool) random_int($min = 0, $max = 1);
                $this->matrix[$i][$j] = new $this->cell_type($rnd_is_alive);
            }
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
//----------------------------------------------------------------------------
//  main
//----------------------------------------------------------------------------
function action_usage(array $argv):void {
    echo "{$argv[0]} [act1|act2] [param1]
actions:
    run = run the game
    test = unit test of internals
\n";
}
// run unit tests of the procedure
function action_autotest(array $argv):void {

}

// run the game or the unit tests
function main(int $argc, array $argv):void {
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
