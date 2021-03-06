<?php
namespace PharIo\Phive;

class PharService {

    /**
     * @var PharDownloader
     */
    private $downloader;

    /**
     * @var PharInstaller
     */
    private $installer;

    /**
     * @var PharRegistry
     */
    private $pharRegistry;

    /**
     * @var AliasResolverService
     */
    private $aliasResolver;

    /**
     * @var Cli\Output
     */
    private $output;

    /**
     * @var SourceRepositoryLoader
     */
    private $sourceRepositoryLoader;

    /**
     * @param PharDownloader         $downloader
     * @param PharInstaller          $installer
     * @param PharRegistry           $pharRegistry
     * @param AliasResolverService   $resolver
     * @param Cli\Output             $output
     * @param SourceRepositoryLoader $sourceRepositoryLoader
     */
    public function __construct(
        PharDownloader $downloader,
        PharInstaller $installer,
        PharRegistry $pharRegistry,
        AliasResolverService $resolver,
        Cli\Output $output,
        SourceRepositoryLoader $sourceRepositoryLoader
    ) {
        $this->downloader = $downloader;
        $this->installer = $installer;
        $this->pharRegistry = $pharRegistry;
        $this->aliasResolver = $resolver;
        $this->output = $output;
        $this->sourceRepositoryLoader = $sourceRepositoryLoader;
    }

    /**
     * @param RequestedPhar $requestedPhar
     * @param Directory $destination
     * @param bool $makeCopy
     *
     * @return Phar|null
     */
    public function install(RequestedPhar $requestedPhar, Directory $destination, $makeCopy) {
        return $this->doInstall($requestedPhar, $destination, $makeCopy, false);
    }

    /**
     * @param RequestedPhar $requestedPhar
     * @param Directory $destination
     *
     * @return Phar|null
     */
    public function update(RequestedPhar $requestedPhar, Directory $destination) {
        return $this->doInstall($requestedPhar, $destination, false, true);
    }

    /**
     * @param RequestedPhar $requestedPhar
     * @param Directory $destination
     * @param bool $makeCopy
     * @param bool $replaceExisting
     *
     * @return Phar|null
     */
    private function doInstall(RequestedPhar $requestedPhar, Directory $destination, $makeCopy, $replaceExisting) {
        $release = $this->getRelease($requestedPhar);
        
        $name = $release->getName();
        $version = $release->getVersion();
        $pharName = $this->getPharName($release->getUrl());

        $destination = $destination->file($pharName);
        if (!$replaceExisting && $destination->exists()) {
            $this->output->writeInfo(sprintf('%s is already installed, skipping.', $pharName));
            return null;
        }

        if (!$this->pharRegistry->hasPhar($name, $version)) {
            $phar = $this->downloader->download($release);
            $this->pharRegistry->addPhar($phar);
        } else {
            $phar = $this->pharRegistry->getPhar($name, $version);
        }
        $this->installer->install($phar->getFile(), $destination, $makeCopy);
        $this->pharRegistry->addUsage($phar, $destination);

        return $phar;
    }

    /**
     * @param RequestedPhar $requestedPhar
     *
     * @throws DownloadFailedException
     * @throws ResolveException
     *
     * @return Release
     */
    private function getRelease(RequestedPhar $requestedPhar) {
        if ($requestedPhar->isAlias()) {
            return $this->resolveAlias($requestedPhar->getAlias());
        }

        $url = $requestedPhar->getPharUrl();

        return new Release($this->getPharName($url), $this->getPharVersion($url), $url, null);
    }

    /**
     * @param PharAlias $alias
     *
     * @return Release
     * @throws InstallationFailedException
     * @throws ResolveException
     *
     */
    private function resolveAlias(PharAlias $alias) {
        foreach ($this->aliasResolver->resolve($alias) as $source) {
            try {
                $repo = $this->sourceRepositoryLoader->loadRepository($source);
                $releases = $repo->getReleasesByAlias($alias);
                return $releases->getLatest($alias->getVersionConstraint());
            } catch (ResolveException $e) {
                $this->output->writeWarning(
                    sprintf(
                        'Resolving alias %s with repository %s failed: %s',
                        $alias,
                        $source->getUrl(),
                        $e->getMessage()
                    )
                );
                continue;
            }
        }
        throw new ResolveException(sprintf('Could not resolve alias %s', $alias));
    }

    /**
     * @param Url $url
     *
     * @return Version
     * @throws DownloadFailedException
     */
    private function getPharVersion(Url $url) {
        $filename = pathinfo((string)$url, PATHINFO_FILENAME);
        preg_match('/-([\d]+.[\d]+.[\d]+.*)/', $filename, $matches);
        if (count($matches) !== 2) {
            preg_match('/\/([\d]+.[\d]+.[\d]+.*)\//', (string)$url, $matches);
        }
        if (count($matches) !== 2) {
            throw new DownloadFailedException(sprintf('Could not extract PHAR version from %s', $url));
        }

        return new Version($matches[1]);
    }

    /**
     * @param Url $url
     *
     * @return string
     * @throws DownloadFailedException
     */
    private function getPharName(Url $url) {
        $filename = pathinfo((string)$url, PATHINFO_FILENAME);
        preg_match('/(.*)-[\d]+.[\d]+.[\d]+.*/', $filename, $matches);
        if (count($matches) !== 2) {
            $matches[1] = $filename;
        }

        return $matches[1];
    }
}
