<?php

/*
 * Preset di architettura condiviso di scrapkit/engineering-kit: niente funzioni
 * di debug nel codice di produzione, più i preset `php` e `security` di Pest.
 * Le expectation proprie di Deskr si aggiungono qui sotto.
 */

require_once __DIR__.'/../vendor/scrapkit/engineering-kit/configs/testing/pest.php';

scrapkit_arch_preset();
