<?php
namespace FrealessInstaller;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use ZipArchive;

class InstallerCommand extends \Symfony\Component\Console\Command\Command
{
    protected $repoUrl = "https://github.com/ahmedalaahagag/tiny-framework/archive/master.zip";

    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Creates a new app from a repo.')
            ->setHelp('This command allows you to create a new from a repo ..')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of app.');
    }

    protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output)
    {

        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The Zip PHP extension is not installed. Please install it and try again.');
        }

        $directory = ($input->getArgument('name')) ? getcwd() . '/' . $input->getArgument('name') : getcwd();

        $output->writeln('<info>Creating your awesome app</info>');
        $this->download($zipFile = $this->makeFilename())
            ->extract($zipFile, $directory)
            ->prepareWritableDirectories($directory, $output)
            ->cleanUp($zipFile);

        $composer = $this->findComposer();


        $commands = [
            "cd ..",
            "cd " . $directory . "/tiny-framework-master",
            "mv -v * ..",
            $composer . ' update',
        ];

        $process = new Process(implode(' && ', $commands), $directory, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        $output->writeln('<comment>Awesome '.$input->getArgument('name').' App Created</comment>');
    }


    protected function makeFilename()
    {
        return getcwd() . '/frealess_' . md5(time() . uniqid()) . '.zip';
    }

    protected function download($zipFile)
    {


        $response = (new \GuzzleHttp\Client())->get($this->repoUrl);

        file_put_contents($zipFile, $response->getBody());

        return $this;
    }

    protected function extract($zipFile, $directory)
    {
        $archive = new ZipArchive;

        $archive->open($zipFile);

        $archive->extractTo($directory);

        $archive->close();

        return $this;
    }

    protected function prepareWritableDirectories($appDirectory, \Symfony\Component\Console\Output\OutputInterface $output)
    {
        $filesystem = new \Symfony\Component\Filesystem\Filesystem();

        try {
            $filesystem->chmod($appDirectory . DIRECTORY_SEPARATOR, 0755, 0000, true);
        } catch (IOExceptionInterface $e) {
            $output->writeln('<comment>You should verify that directories are writable.</comment>');
        }

        return $this;
    }

    protected
    function cleanUp($zipFile)
    {
        @chmod($zipFile, 0777);

        @unlink($zipFile);

        return $this;
    }

    protected function findComposer()
    {
        if (file_exists(getcwd() . '/composer.phar')) {
            return '"' . PHP_BINARY . '" composer.phar';
        }

        return 'composer';
    }

}