<?php namespace Testomatio;

class ImportCest
{
    // tests
    public function analyzeTests(CliTester $I)
    {
        $I->runShellCommand('php tests-preview tests/_data');
        $I->seeInShellOutput('Found 3 tests');
        $I->seeInShellOutput('@important');
        $I->seeInShellOutput('EmptyTest');
        $I->seeInShellOutput('One');
    }

}
