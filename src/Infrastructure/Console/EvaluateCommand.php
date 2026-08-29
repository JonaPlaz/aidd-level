<?php

declare(strict_types=1);

namespace AiddLevel\Infrastructure\Console;

use AiddLevel\Application\EvaluateProfile;
use AiddLevel\Application\EvaluateProfileHandler;
use AiddLevel\Domain\AssessmentStatus;
use AiddLevel\Infrastructure\Render\TextRenderer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `evaluate <profile-dir>...` (docs/specs/00-vue-ensemble.md § 6). One or more profile
 * folders, handler → renderer → output, in the order given on the command line. A profile
 * that fails the gate never stops the batch (docs/specs/00-vue-ensemble.md § 6: "Un profil
 * qui échoue au gate n'arrête pas l'évaluation des autres") — `EvaluateProfileHandler::handle`
 * already turns a broken gate into a `NotAssessable` `Assessment`, this command only decides
 * the process exit code from the resulting statuses.
 *
 * Without an argument, the command lists the profile folders shipped in `profiles/`
 * (docs/specs/00-vue-ensemble.md § 6), looked up relative to the working directory first,
 * then relative to the project root — so `docker run --rm aidd-level evaluate` (WORKDIR
 * `/app`, § 6 lancement de référence) and a plain `bin/aidd-level evaluate` run from the
 * repository root both find it, without hard-coding either path.
 */
#[AsCommand(name: 'evaluate', description: "Évalue le niveau AIDD d'un ou plusieurs profils")]
final class EvaluateCommand extends Command
{
    private const string PROFILE_DIR_ARGUMENT = 'profile-dir';

    // A dashed rule between two profile outputs in the same batch, wide enough to stand out
    // from the renderer's own text without competing with `TextRenderer::MAX_WIDTH` (100).
    private const string SEPARATOR = '────────────────────────────────────────────────────────────';

    public function __construct(
        private readonly EvaluateProfileHandler $handler,
        private readonly TextRenderer $renderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            self::PROFILE_DIR_ARGUMENT,
            InputArgument::IS_ARRAY,
            'Un ou plusieurs dossiers de profil (docs/specs/00-vue-ensemble.md § 3)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $paths */
        $paths = $input->getArgument(self::PROFILE_DIR_ARGUMENT);

        if ([] === $paths) {
            return $this->listProfiles($output);
        }

        $evaluatedCount = 0;

        foreach ($paths as $index => $path) {
            if (0 !== $index) {
                $output->writeln(self::SEPARATOR);
            }

            $assessment = $this->handler->handle(new EvaluateProfile($path));
            $output->write($this->renderer->render($assessment));

            if (AssessmentStatus::NotAssessable !== $assessment->status) {
                ++$evaluatedCount;
            }
        }

        return $evaluatedCount > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function listProfiles(OutputInterface $output): int
    {
        $profilesDir = $this->locateProfilesDirectory();

        if (null === $profilesDir) {
            $output->writeln('Aucun dossier profiles/ trouvé — indiquer un dossier de profil en argument.');

            return Command::FAILURE;
        }

        $names = $this->subdirectories($profilesDir);

        $output->writeln(sprintf('Dossiers de profil disponibles dans %s :', $profilesDir));
        if ([] === $names) {
            $output->writeln('  (aucun)');

            return Command::SUCCESS;
        }

        foreach ($names as $name) {
            $output->writeln(sprintf('  %s', $name));
        }

        $output->writeln('');
        $output->writeln(sprintf('Usage : bin/aidd-level evaluate %s/<nom> [...]', rtrim($profilesDir, '/')));

        return Command::SUCCESS;
    }

    /**
     * Working directory first, project root second (docs/specs/00-vue-ensemble.md § 6). The
     * project root is derived from this file's own location (`src/Infrastructure/Console/` is
     * three levels below it) rather than `getcwd()`, so it stays correct however the command
     * is invoked.
     */
    private function locateProfilesDirectory(): ?string
    {
        $candidates = ['profiles', \dirname(__DIR__, 3).'/profiles'];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function subdirectories(string $path): array
    {
        $entries = scandir($path);
        if (false === $entries) {
            return [];
        }

        $directories = [];
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            if (is_dir($path.'/'.$entry)) {
                $directories[] = $entry;
            }
        }

        sort($directories);

        return $directories;
    }
}
