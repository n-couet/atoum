<?php

namespace mageekguy\atoum\scripts;

use
	mageekguy\atoum,
	mageekguy\atoum\exceptions
;

class treemap extends atoum\script
{
	protected $projectName = null;
	protected $directories = array();
	protected $outputFile = null;
	protected $analyzers = array();
	protected $includer = null;
	protected $run = true;

	public function __construct($name, atoum\adapter $adapter = null)
	{
		parent::__construct($name, $adapter);

		$this->setIncluder();
	}

	public function help()
	{
		$this->run = false;

		return parent::help();
	}

	public function setProjectName($projectName)
	{
		$this->projectName = $projectName;

		return $this;
	}

	public function getProjectName()
	{
		return $this->projectName;
	}

	public function addDirectory($directory)
	{
		if (in_array($directory, $this->directories) === false)
		{
			$this->directories[] = $directory;
		}

		return $this;
	}

	public function getDirectories()
	{
		return $this->directories;
	}

	public function setOutputFile($file)
	{
		$this->outputFile = $file;

		return $this;
	}

	public function getOutputFile()
	{
		return $this->outputFile;
	}

	public function getAnalyzers()
	{
		return $this->analyzers;
	}

	public function addAnalyzer(treemap\analyzer $analyzer)
	{
		$this->analyzers[] = $analyzer;

		return $this;
	}

	public function setIncluder(atoum\includer $includer = null)
	{
		$this->includer = $includer ?: new atoum\includer();

		return $this;
	}

	public function getIncluder()
	{
		return $this->includer;
	}

	public function useConfigFile($path)
	{
		$script = $this;

		try
		{
			$this->includer->includePath($path, function($path) use ($script) { include_once($path); });
		}
		catch (atoum\includer\exception $exception)
		{
			throw new atoum\includer\exception(sprintf($this->getLocale()->_('Unable to find configuration file \'%s\''), $path));
		}

		return $this;
	}

	public function run(array $arguments = array())
	{
		parent::run($arguments);

		if ($this->run === true)
		{
			if ($this->projectName === null)
			{
				throw new exceptions\runtime($this->locale->_('Project name is undefined'));
			}

			if (sizeof($this->directories) <= 0)
			{
				throw new exceptions\runtime($this->locale->_('Directories are undefined'));
			}

			if ($this->outputFile === null)
			{
				throw new exceptions\runtime($this->locale->_('Output file is undefined'));
			}

			$maxDepth = 0;

			$rootData = array('name' => $this->projectName, 'path' => '', 'children' => array(), 'maxDepth' => & $maxDepth);

			foreach ($this->directories as $directory)
			{
				try
				{
					$directoryIterator = new \recursiveIteratorIterator(new atoum\iterators\filters\recursives\dot($directory));
				}
				catch (\exception $exception)
				{
					throw new exceptions\runtime($this->locale->_('Directory \'' . $directory . '\' does not exist'));
				}

				foreach ($directoryIterator as $file)
				{
					$data = & $rootData;

					$directories = ltrim(substr(dirname($file->getPathname()), strlen($directory)), DIRECTORY_SEPARATOR);

					if ($directories !== '')
					{
						$directories = explode(DIRECTORY_SEPARATOR, $directories);

						$depth = sizeof($directories);

						if ($depth > $maxDepth)
						{
							$maxDepth = $depth;
						}

						foreach ($directories as $directory)
						{
							$childFound = false;

							foreach ($data['children'] as $key => $child)
							{
								if ($child['name'] === $directory)
								{
									$childFound = true;
									break;
								}
							}

							if ($childFound === false)
							{
								$key = sizeof($data['children']);
								$data['children'][] = array(
									'name' => $directory,
									'path' => $data['path'] . DIRECTORY_SEPARATOR . $directory,
									'children' => array()
								);
							}

							$data = & $data['children'][$key];
						}
					}

					$child = array(
						'name' => $file->getFilename(),
						'path' => $data['path'] . DIRECTORY_SEPARATOR . $file->getFilename(),
						'metrics' => array()
					);

					foreach ($this->analyzers as $analyzer)
					{
						$child['metrics'][$analyzer->getMetricName()] = $analyzer->getMetricFromFile($file);
					}

					$data['children'][] = $child;
				}
			}

			if (@file_put_contents($this->outputFile, json_encode($rootData)) === false)
			{
				throw new exceptions\runtime($this->locale->_('Unable to write in \'' . $this->outputFile . '\''));
			}
		}

		return $this;
	}

	protected function setArgumentHandlers()
	{
		return $this
			->addArgumentHandler(
				function($script, $argument, $values) {
					if (sizeof($values) != 0)
					{
						throw new exceptions\logic\invalidArgument(sprintf($script->getLocale()->_('Bad usage of %s, do php %s --help for more informations'), $argument, $script->getName()));
					}

					$script->help();
				},
				array('-h', '--help'),
				null,
				$this->locale->_('Display this help')
			)
			->addArgumentHandler(
				function($script, $argument, $outputFile) {
					if (sizeof($outputFile) != 1)
					{
						throw new exceptions\logic\invalidArgument(sprintf($script->getLocale()->_('Bad usage of %s, do php %s --help for more informations'), $argument, $script->getName()));
					}

					$script->setOutputFile(current($outputFile));
				},
				array('-of', '--output-file'),
				'<file>',
				$this->locale->_('Save data in file <file>')
			)
			->addArgumentHandler(
				function($script, $argument, $directories) {
					if (sizeof($directories) <= 0)
					{
						throw new exceptions\logic\invalidArgument(sprintf($script->getLocale()->_('Bad usage of %s, do php %s --help for more informations'), $argument, $script->getName()));
					}

					foreach ($directories as $directory)
					{
						$script->addDirectory($directory);
					}
				},
				array('-d', '--directories'),
				'<directory>...',
				$this->locale->_('Scan all directories <directory>')
			)
			->addArgumentHandler(
				function($script, $argument, $projectName) {
					if (sizeof($projectName) != 1)
					{
						throw new exceptions\logic\invalidArgument(sprintf($script->getLocale()->_('Bad usage of %s, do php %s --help for more informations'), $argument, $script->getName()));
					}

					$script->setProjectName(current($projectName));
				},
				array('-pn', '--project-name'),
				'<string>',
				$this->locale->_('Set project name <string>')
			)
			->addArgumentHandler(
					function($script, $argument, $files) {
						if (sizeof($files) <= 0)
						{
							throw new exceptions\logic\invalidArgument(sprintf($script->getLocale()->_('Bad usage of %s, do php %s --help for more informations'), $argument, $script->getName()));
						}

						foreach ($files as $path)
						{
							try
							{
								$script->useConfigFile($path);
							}
							catch (includer\exception $exception)
							{
								throw new exceptions\logic\invalidArgument(sprintf($script->getLocale()->_('Configuration file \'%s\' does not exist'), $path));
							}
						}
					},
					array('-c', '--configurations'),
					'<file>...',
					$this->locale->_('Use all configuration files <file>'),
					1
				)
		;
	}
}