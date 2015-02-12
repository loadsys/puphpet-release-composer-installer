<?php

namespace Loadsys\Composer;

// Needed for LibraryInstaller:
use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;

// Needed for copying the release folder to the root.
use \RecursiveDirectoryIterator;
use \RecursiveCallbackFilterIterator;
use \RecursiveIteratorIterator;

if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

/**
 * Custom installer and event handler.
 *
 * Ensures that a package with type=puphpet-release has its `release/`
 * subfolder copied into the project root, and associated configs copied
 * into the `puphpet/` folder afterwards.
 */
class PuphpetReleaseInstaller extends LibraryInstaller {

    /**
     * Defines the `type`s of composer packages to which this installer applies.
     *
     * A project's composer.json file must specify `"type": "puphpet-release"`
     * in order to trigger this installer.
     *
     * @param string $packageType The `type` specified in the consuming project's composer.json.
     * @return bool True if this installer should be activated for the package in question, false if not.
     */
    public function supports($packageType) {
        return 'puphpet-release' === $packageType;
    }

	/**
     * Override LibraryInstaller::installCode() to hook in additional post-download steps.
     *
     * @param InstalledRepositoryInterface $repo    repository in which to check
     * @param PackageInterface             $package package instance
     */
	protected function installCode(PackageInterface $package) {
		parent::installCode($package);

		if (!$this->supports($package->getType())) {
			return;
		}
		$this->copyReleaseItems($package);
		$this->copyConfigFile($package);
		$this->checkGitignore($package);
	}

	/**
     * Copy items from the installed package's release/ folder into the
     * target directory.
     *
     */
	protected function copyReleaseItems($package) {
		// Copy everything from the release/ subfolder to the project root.
		$targetDir = getcwd();
		$releaseDir = $this->getInstallPath($package) . DS . 'release';
		$acceptList = [
			'Vagrantfile',
			'puphpet',
		];

		// Return true if the first part of the subpath for the current file exists in the accept array.
		$acceptFunc = function ($current, $key, $iterator) use ($acceptList) {
			$pathComponents = explode(DS, $iterator->getSubPathname());
			return in_array($pathComponents[0], $acceptList);
		};
		$dirIterator = new RecursiveDirectoryIterator($releaseDir, RecursiveDirectoryIterator::SKIP_DOTS);
		$filterIterator = new RecursiveCallbackFilterIterator($dirIterator, $acceptFunc);
		$releaseItems = new RecursiveIteratorIterator($filterIterator, RecursiveIteratorIterator::SELF_FIRST);

		foreach ($releaseItems as $file) {
			// Ref: http://stackoverflow.com/a/20092223/70876
			if ($file->isDir()) {
				mkdir($targetDir . DS . $releaseItems->getSubPathName());
			} else {
				copy($file, $targetDir . DS . $releaseItems->getSubPathName());
			}
		}
	}

	/**
     * Search for a config file in the consuming project and copy it into
     * place if present.
     *
     */
	protected function copyConfigFile($package) {
		$configFilePath = getcwd() . DS . 'puphpet.yaml';
		$targetPath = getcwd() . DS . 'puphpet' . DS . 'config.yaml';
		if (is_readable($configFilePath)) {
			copy($configFilePath, $targetPath);
		}
	}

    /**
     * Check that release items copied into the consuming project are
     * properly ignored in source control (very, VERY crudely.)
     *
     */
	protected function checkGitIgnore($package) {
		$gitignoreFile = getcwd() . DS . '.gitignore';
		$required = [
			'/Vagrantfile',
			'/puphpet/',
		];

		try {
			$lines = file($gitignoreFile, FILE_IGNORE_NEW_LINES);
		} catch (Exception $e) {
			return;
		}

		foreach ($required as $entry) {
			if (!in_array($entry, $lines)) {
				$lines[] = $entry;
			}
		}
		file_put_contents($gitignoreFile, implode(PHP_EOL, $lines));
	}
}