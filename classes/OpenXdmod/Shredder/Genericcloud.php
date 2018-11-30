<?php
/**
 * Generic cloud event data shredder.
 *
 * @author Greg Dean <gmdean@ccr.buffalo.edu>
 */

namespace OpenXdmod\Shredder;

class Genericcloud extends aCloud
{
    /**
     * @inheritdoc
     */
    public function shredDirectory($directory)
    {
        return parent::shredDirectory($directory, ['jobs-cloud-ingest-eucalyptus']);
    }
}
