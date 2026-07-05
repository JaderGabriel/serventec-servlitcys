<?php

namespace App\Support\Horizonte;

/** Converte selecção de fases (opt-in) em skip options do feed Horizonte. */
final class HorizonteFeedPhaseOptions
{
    /**
     * @param  list<string>  $selectedPhaseKeys
     * @return array<string, bool>
     */
    public static function skipOptionsFromSelectedPhases(array $selectedPhaseKeys): array
    {
        $selected = array_flip(array_values(array_filter(array_map('strval', $selectedPhaseKeys))));

        $options = [];
        foreach (HorizonteFortnightlyFeedPhaseCatalog::definitions() as $def) {
            $options[$def['skip_option']] = ! isset($selected[$def['key']]);
        }

        return $options;
    }

    /**
     * @param  list<string>  $selectedPhaseKeys
     * @return list<string>
     */
    public static function orderedQueueFromSelectedPhases(array $selectedPhaseKeys): array
    {
        $selected = array_flip(array_values(array_filter(array_map('strval', $selectedPhaseKeys))));
        $queue = [];
        foreach (HorizonteFortnightlyFeedPhaseCatalog::definitions() as $def) {
            if (isset($selected[$def['key']])) {
                $queue[] = $def['key'];
            }
        }

        return $queue;
    }

    /**
     * @return list<string>
     */
    public static function defaultSelectedPhaseKeys(): array
    {
        return array_column(HorizonteFortnightlyFeedPhaseCatalog::definitions(), 'key');
    }
}
