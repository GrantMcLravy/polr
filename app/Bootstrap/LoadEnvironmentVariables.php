<?php

namespace App\Bootstrap;

use Dotenv\Repository\RepositoryInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables as BaseLoadEnvironmentVariables;
use Illuminate\Support\Env;

/**
 * Load .env the way Polr 2.x did (vlucas/phpdotenv 1.1.1).
 *
 * Laravel 13 uses phpdotenv 5, which refuses to parse files that phpdotenv 1
 * accepted, e.g. unquoted values containing spaces (APP_NAME=My Links) or
 * backslashes in double-quoted values (written by Polr's setup wizard for
 * passwords containing "\"). An unparseable .env stops the whole application,
 * so existing installs could go down on upgrade. phpdotenv 5 also expands
 * ${VAR} inside double quotes, which phpdotenv 1 left alone, changing values.
 *
 * The parsing below is ported from phpdotenv 1.1.1 so that every existing
 * .env file yields exactly the same values (see baseline/fixtures/env). As in
 * phpdotenv 1 and Laravel, variables already set in the real environment take
 * precedence over .env. A missing .env is ignored, as in Laravel.
 *
 * Portions derived from vlucas/phpdotenv 1.1.1:
 * Copyright (c) 2013, Vance Lucas. All rights reserved. BSD 3-Clause License.
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * redistributions of source code must retain the above copyright notice, this
 * list of conditions and the following disclaimer; redistributions in binary
 * form must reproduce the above copyright notice, this list of conditions and
 * the following disclaimer in the documentation and/or other materials
 * provided with the distribution; neither the name of the copyright holder nor
 * the names of its contributors may be used to endorse or promote products
 * derived from this software without specific prior written permission.
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */
class LoadEnvironmentVariables extends BaseLoadEnvironmentVariables
{
    public function bootstrap(Application $app)
    {
        if ($app->configurationIsCached()) {
            return;
        }

        $this->checkForSpecificEnvironmentFile($app);

        static::load($app->environmentFilePath(), Env::getRepository());
    }

    /**
     * Load the variables in $filePath into $repository, without overwriting
     * variables that are already set.
     */
    public static function load(string $filePath, RepositoryInterface $repository): void
    {
        if (! is_readable($filePath) || ! is_file($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Disregard comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            // Only use non-empty lines that look like setters
            if (strpos($line, '=') === false) {
                continue;
            }

            [$name, $value] = array_map('trim', explode('=', $line, 2));
            $name = static::sanitiseVariableName($name);
            $value = static::sanitiseVariableValue($value);
            $value = static::resolveNestedVariables($value, $repository);

            // Don't overwrite existing environment variables
            if ($name === '' || $repository->has($name)) {
                continue;
            }

            $repository->set($name, $value);
        }
    }

    protected static function sanitiseVariableValue(string $value): string
    {
        $value = trim($value);
        if (! $value) {
            // (As in phpdotenv 1, this includes the value "0".)
            return '';
        }
        if (strpbrk($value[0], '"\'') !== false) { // value starts with a quote
            $quote = $value[0];
            $regexPattern = sprintf('/^
                %1$s          # match a quote at the start of the value
                (             # capturing sub-pattern used
                 (?:          # we do not need to capture this
                  [^%1$s\\\\] # any character other than a quote or backslash
                  |\\\\\\\\   # or two backslashes together
                  |\\\\%1$s   # or an escaped quote e.g \"
                 )*           # as many characters that match the previous rules
                )             # end of the capturing sub-pattern
                %1$s          # and the closing quote
                .*$           # and discard any string after the closing quote
                /mx', $quote);
            $value = preg_replace($regexPattern, '$1', $value);
            $value = str_replace("\\$quote", $quote, $value);
            $value = str_replace('\\\\', '\\', $value);
        } else {
            $parts = explode(' #', $value, 2);
            $value = $parts[0];
        }

        return trim($value);
    }

    protected static function sanitiseVariableName(string $name): string
    {
        return trim(str_replace(['export ', '\'', '"'], '', $name));
    }

    /**
     * phpdotenv 1 expanded {$NAME} (not ${NAME}) using variables already set.
     */
    protected static function resolveNestedVariables(string $value, RepositoryInterface $repository): string
    {
        if (strpos($value, '$') !== false) {
            $value = preg_replace_callback(
                '/{\$([a-zA-Z0-9_]+)}/',
                function ($matchedPatterns) use ($repository) {
                    $nestedVariable = $repository->get($matchedPatterns[1]);

                    return $nestedVariable === null ? $matchedPatterns[0] : $nestedVariable;
                },
                $value
            );
        }

        return $value;
    }
}
