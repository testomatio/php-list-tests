<?php
namespace Testomatio;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends SymfonyCommand
{
    protected static $defaultName = 'check-tests';

    protected function configure()
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to scan for tests');
        $this->addOption('markdown', 'm',InputOption::VALUE_REQUIRED, 'Save data information to markdown file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // ... put here the code to run in your command

        $output->writeln("Printing tests from <comment>" . $input->getArgument('path') . '</comment>');
        $output->writeln('This may take some time on large projects...');
        $output->writeln('');

        $checkTests = new CheckTests();
        $checkTests->analyze($input->getArgument('path'));
        $tests = $checkTests->getTests();
        $numTests = count($tests);

        $printer = new Printer($tests);
        $printer->printToConsole($output);

        if ($file = $input->getOption('markdown')) {
            $output->writeln("Saving tests information to <comment>$file</comment>");
            file_put_contents($file, $printer->printToMarkown());
        }

        $output->writeln("<info>Found $numTests tests</info>");

        $errors = $checkTests->getErrors();

        if (count($errors)) {
            $output->writeln(sprintf('There were %d issues while importing tests', count($errors)));
            if ($output->isVerbose()) {
                foreach ($errors as $error) {
                    $output->writeln("<error>$error</error>");
                }
            } else {
                $output->writeln('Run this command in --verbose mode to see them');
            }
        }


        if (!getenv('TESTOMATIO')) {
            return 1;
        }
        $output->writeln("<comment>Sending data to Testomat.io...</comment>\n");
        $request = new Request();
        try {
            $request->sendTests($tests);
        } catch (\Throwable $e) {
            $output->writeln("Can't send data to Testomat.io\n");
            $output->writeln("<error>{$e->getMessage()}</error>");
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
        $output->writeln("<info>Data received by Testomat.io</info>");
        return 0;
    }
}
