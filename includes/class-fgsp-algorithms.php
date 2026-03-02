<?php
/**
 * Core scheduling algorithms for fixture generation.
 */

if (!defined('ABSPATH')) {
    exit;
}

class FGSP_Algorithms
{
    /**
     * Round Robin scheduling algorithm.
     *
     * @param array $teams Array of team IDs.
     * @param bool $balance Balance home/away matches.
     * @return array Array of rounds, each containing an array of matches.
     */
    public static function generate_round_robin($teams, $balance = true)
    {
        if (count($teams) % 2 != 0) {
            $teams[] = null; // bye
        }
        $n = count($teams);
        $rounds = array();
        for ($r = 0; $r < $n - 1; $r++) {
            $round_matches = array();
            for ($i = 0; $i < $n / 2; $i++) {
                $home = $teams[$i];
                $away = $teams[$n - 1 - $i];
                if ($home !== null && $away !== null) {
                    // Balancing home/away
                    if ($balance && ($i === 0 ? ($r % 2 === 0) : (($i + $r) % 2 === 0))) {
                        $round_matches[] = array($away, $home);
                    } else {
                        $round_matches[] = array($home, $away);
                    }
                }
            }
            $rounds[] = $round_matches;

            // Rotate
            $last = array_pop($teams);
            array_splice($teams, 1, 0, array($last));
        }
        return $rounds;
    }

    /**
     * Single Elimination Playoff scheduling algorithm.
     *
     * @param array $teams Array of team IDs.
     * @return array Array of rounds, each containing an array of matches.
     */
    public static function generate_playoff($teams)
    {
        $n = count($teams);
        $rounds = array();

        // Round 1 (Seed pairings: 1 vs N, 2 vs N-1, etc.)
        $r1_matches = array();
        for ($i = 0; $i < $n / 2; $i++) {
            $r1_matches[] = array($teams[$i], $teams[$n - 1 - $i]);
        }
        $rounds[] = $r1_matches;

        // Note: For playoffs, generating Round 2 and beyond is complex because 
        // teams aren't known yet. We'll generate placeholders or just the first round.
        // For now, let's just generate the first round of the playoffs (Quarterfinals/Semifinals).
        return $rounds;
    }

    /**
     * Random Knockout scheduling algorithm.
     *
     * @param array $teams Array of team IDs.
     * @return array Array of rounds, each containing an array of matches.
     */
    public static function generate_knockout($teams)
    {
        if (count($teams) % 2 != 0) {
            $teams[] = null; // bye
        }
        shuffle($teams);
        $matches = array();
        for ($i = 0; $i < count($teams); $i += 2) {
            if ($teams[$i] !== null && $teams[$i + 1] !== null) {
                $matches[] = array($teams[$i], $teams[$i + 1]);
            }
        }
        return array($matches);
    }
}
