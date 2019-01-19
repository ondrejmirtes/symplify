<?php declare(strict_types=1);

namespace Symplify\Statie\MigratorJekyll;

use Symplify\Statie\MigratorJekyll\Configuration\MigratorOption;
use Symplify\Statie\MigratorJekyll\Filesystem\FilesystemMover;
use Symplify\Statie\MigratorJekyll\Filesystem\FilesystemRegularApplicator;
use Symplify\Statie\MigratorJekyll\Filesystem\FilesystemRemover;
use Symplify\Statie\MigratorJekyll\Worker\IncludePathsCompleter;
use Symplify\Statie\MigratorJekyll\Worker\ParametersAdder;
use Symplify\Statie\MigratorJekyll\Worker\PostIdsAdder;
use Symplify\Statie\MigratorJekyll\Worker\StatieImportsAdder;
use Symplify\Statie\MigratorJekyll\Worker\TwigSuffixChanger;

final class JekyllToStatieMigrator
{
    /**
     * @var mixed[]
     */
    private $migratorJekyll = [];

    /**
     * @var StatieImportsAdder
     */
    private $statieImportsAdder;

    /**
     * @var IncludePathsCompleter
     */
    private $includePathsCompleter;

    /**
     * @var PostIdsAdder
     */
    private $postIdsAdder;

    /**
     * @var TwigSuffixChanger
     */
    private $twigSuffixChanger;

    /**
     * @var ParametersAdder
     */
    private $parametersAdder;

    /**
     * @var FilesystemMover
     */
    private $filesystemMover;

    /**
     * @var FilesystemRemover
     */
    private $filesystemRemover;

    /**
     * @var FilesystemRegularApplicator
     */
    private $filesystemRegularApplicator;

    /**
     * @param mixed[] $migratorJekyll
     */
    public function __construct(
        array $migratorJekyll,
        StatieImportsAdder $statieImportsAdder,
        IncludePathsCompleter $includePathsCompleter,
        PostIdsAdder $postIdsAdder,
        TwigSuffixChanger $twigSuffixChanger,
        ParametersAdder $parametersAdder,
        FilesystemMover $filesystemMover,
        FilesystemRemover $filesystemRemover,
        FilesystemRegularApplicator $filesystemRegularApplicator
    ) {
        $this->statieImportsAdder = $statieImportsAdder;
        $this->includePathsCompleter = $includePathsCompleter;
        $this->postIdsAdder = $postIdsAdder;
        $this->twigSuffixChanger = $twigSuffixChanger;
        $this->parametersAdder = $parametersAdder;
        $this->filesystemMover = $filesystemMover;
        $this->filesystemRemover = $filesystemRemover;
        $this->filesystemRegularApplicator = $filesystemRegularApplicator;
        $this->migratorJekyll = $migratorJekyll;
    }

    public function migrate(string $workingDirectory): void
    {
        $workingDirectory = rtrim($workingDirectory, '/');

        // 1. remove unwated files
        if ($this->migratorJekyll[MigratorOption::PATHS_TO_REMOVE]) {
            $this->filesystemRemover->processPaths(
                $workingDirectory,
                $this->migratorJekyll[MigratorOption::PATHS_TO_REMOVE]
            );
        }

        // 2. move files, rename
        if ($this->migratorJekyll[MigratorOption::PATHS_TO_MOVE]) {
            $this->filesystemMover->processPaths(
                $workingDirectory,
                $this->migratorJekyll[MigratorOption::PATHS_TO_MOVE]
            );
        }

        // now all website files are in "/source" directory

        // 3. clear regulars by paths
        if ($this->migratorJekyll[MigratorOption::APPLY_REGULAR_IN_PATHS]) {
            $this->filesystemRegularApplicator->processPaths(
                $workingDirectory,
                $this->migratorJekyll[MigratorOption::APPLY_REGULAR_IN_PATHS]
            );
        }

        $sourceDirectory = $workingDirectory . '/source';

        // 4. prepend yaml files with `parameters`
        $this->parametersAdder->processSourceDirectory($sourceDirectory, $workingDirectory);

        // 5. complete "include" file name to full paths
        $this->includePathsCompleter->processSourceDirectory($sourceDirectory, $workingDirectory);

        // 6. change suffixes - html/md → twig, where there is a "{% X %}" also inside files to be included
        $this->twigSuffixChanger->processSourceDirectory($sourceDirectory, $workingDirectory);

        // 7. complete id to posts
        $this->postIdsAdder->processSourceDirectory($sourceDirectory, $workingDirectory);

        // 8. import .(yml|yaml) data files in statie.yaml
        $this->statieImportsAdder->processSourceDirectory($sourceDirectory, $workingDirectory);
    }
}