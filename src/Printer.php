<?php

namespace Testomatio;

use Spatie\Emoji\Emoji;
use Symfony\Component\Console\Output\Output;

class Printer
{
    private $testData;

    public function __construct($testData)
    {
        $this->testData = $testData;
    }

    public function printToConsole(Output $output)
    {
        $files = [];

        foreach ($this->testData as $test) {
            /** @var $test TestData  **/
            $file = $test->getFile();
            if (!isset($files[$file])) {
                $files[$file] = [];
            }
            $files[$file][] = $test;
        }


        foreach ($files as $file => $tests) {
            $output->writeln(Emoji::paperclip() . ' ' . trim($file, DIRECTORY_SEPARATOR));
            foreach ($tests as $test) {
                /** @var $test TestData  **/
                $tags = implode(' @', $test->getTags());
                if ($tags) {
                    $tags = '<comment>@' . $tags . '</comment>';
                }
                $output->writeln('  ' . Emoji::checkMark() . '  ' . $test->getName() . ' ' . $tags);
            }
            $output->writeln('');
        }
    }

    public function printToMarkown()
    {
        $baseUrl = getenv('PREPEND_URL');
        $files = [];
        $textLines = ["# Tests"];

        foreach ($this->testData as $test) {
            /** @var $test TestData  **/
            $file = $test->getFile();
            if (!isset($files[$file])) {
                $files[$file] = [];
            }
            $files[$file][] = $test;
        }

        $textLines[] = '';

        foreach ($files as $file => $tests) {
            $file = trim($file, DIRECTORY_SEPARATOR);
            $url = $baseUrl ? $baseUrl . '/'. str_replace(DIRECTORY_SEPARATOR, '/', $file) : null;
            $textLines[] = '#### ' . Emoji::paperclip() . ' ' . $this->printMarkdownLink($file, $url);
            foreach ($tests as $test) {
                /** @var $test TestData  **/
                $tags = implode(' @', $test->getTags());
                if ($tags) {
                    $tags = ' @' . $tags;
                }
                $test->getLine();
                $testUrl = $baseUrl ? $url . '#L' . $test->getLine()  : null;
                $textLines[] = '  - ' . Emoji::checkMark() . '  ' . $this->printMarkdownLink($test->getName(), $testUrl) . ' ' . $tags;
            }
            $textLines[] = '';
        }

        return implode("\n", $textLines);
    }

    private function printMarkdownLink($text, $url = null)
    {
        if (!$url) {
            return $text;
        }
        return "[$text]($url)";
    }
}