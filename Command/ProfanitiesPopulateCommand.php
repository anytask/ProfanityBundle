<?php

namespace Vangrg\ProfanityBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Vangrg\ProfanityBundle\Entity\Profanity;
use Vangrg\ProfanityBundle\Storage\ProfanitiesStorageInterface;

/**
 * Class ProfanitiesPopulateCommand.
 */
class ProfanitiesPopulateCommand extends Command
{
    private ManagerRegistry $doctrine;
    private ProfanitiesStorageInterface $profanityStorage;

    // Constructor injection
    public function __construct(ManagerRegistry $doctrine, ProfanitiesStorageInterface $profanityStorage)
    {
        parent::__construct();

        $this->doctrine = $doctrine;
        $this->profanityStorage = $profanityStorage;
    }

    protected function configure(): void
    {
        $this
            ->setName('vangrg:profanities:populate')
            ->setDescription('Load profanities into database.')
            ->addOption(
                'connection',
                null,
                InputOption::VALUE_OPTIONAL,
                'The connection to use for this command. If empty then use default doctrine connection.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connectionName = $input->getOption('connection');

        // Retrieve the appropriate Doctrine entity manager
        $em = (empty($connectionName))
            ? $this->doctrine->getManagerForClass(Profanity::class)
            : $this->doctrine->getManager($connectionName);

        // Retrieve profanities from storage
        $profanities = $this->profanityStorage->getProfanities();

        // Retrieve existing profanities from the database
        $existedWords = $em->getRepository(Profanity::class)->getProfanitiesArray();

        // Filter out already existing words
        $profanities = array_diff($profanities, $existedWords);

        $i = 0;
        foreach ($profanities as $word) {
            $profanity = new Profanity();
            $profanity->setWord($word);

            $em->persist($profanity);

            // Flush and clear every 100 iterations to optimize memory
            if (($i % 100) === 0) {
                $em->flush();
                $em->clear();
            }
            ++$i;
        }

        $em->flush();

        $output->writeln(sprintf('Populated %d words', $i));

        return Command::SUCCESS;
    }
}
