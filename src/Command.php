<?php
namespace Testomatio;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends SymfonyCommand
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'check-tests';

    protected function configure()
    {
        $this->addArgument('path', InputArgument::REQUIRED, 'Path to scan for tests');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // ... put here the code to run in your command

        $output->writeln("Printing tests from <comment>" . $input->getArgument('path') . '</comment>');
        $output->writeln('');

        $checkTests = new CheckTests();
        $checkTests->analyze($input->getArgument('path'));
        $tests = $checkTests->getTests();
        $numTests = count($tests);

        $printer = new Printer($tests);
        $printer->printToConsole($output);

        $output->writeln("<info>Found $numTests tests</info>\n");

        if (!getenv('TESTOMATIO')) {
            return Command::SUCCESS;
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
        return Command::SUCCESS;
    }
}
