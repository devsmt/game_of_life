<?php
declare(strict_types=1);


/*

Si tratta in realtà di un gioco senza giocatori,
intendendo che la sua evoluzione è determinata dal suo stato iniziale,
senza necessità di alcun input da parte di giocatori umani.
Si svolge su una griglia di caselle quadrate (celle) che si estende all'infinito in tutte le direzioni;
questa griglia è detta mondo.
Ogni cella ha 8 vicini, che sono le celle ad essa adiacenti, includendo quelle in senso diagonale.
Ogni cella può trovarsi in due stati: viva o morta (o accesa e spenta, on e off).
Lo stato della griglia evolve in intervalli di tempo discreti, cioè scanditi in maniera netta.
Gli stati di tutte le celle in un dato istante sono usati per calcolare lo stato delle celle all'istante successivo.
Tutte le celle del mondo vengono quindi aggiornate simultaneamente nel passaggio da un istante a quello successivo:
passa così una generazione.

Le transizioni dipendono unicamente dallo stato delle celle vicine in quella generazione:

Qualsiasi cella viva con meno di due celle vive adiacenti muore, come per effetto d'isolamento;
Qualsiasi cella viva con due o tre celle vive adiacenti sopravvive alla generazione successiva;
Qualsiasi cella viva con più di tre celle vive adiacenti muore, come per effetto di sovrappopolazione;
Qualsiasi cella morta con esattamente tre celle vive adiacenti diventa una cella viva, come per effetto di riproduzione.

*/


