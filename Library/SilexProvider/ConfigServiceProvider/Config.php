<?php

namespace SilexProvider\ConfigServiceProvider;

class Config {

    /**
     * @var array The entire config array.
     */
    private $config;

    /**
     * ConfigService Class. Enables to merge configuration data from common
     * config files and environment-specific config files and get a specific
     * value (or all value).
     *
     * @param string $pathToConfigFiles the path to where config files are stored.
     * @param string $env The current environment.
     * @param string $commonFileName The name of the common config. This parameter
     * is required in order to use common data.
     * @param string $filePrefix The prefix used for all filenames. Defaults to
     * "config_".
     */
    public function __construct($pathToConfigFiles, $env, $commonFileName = '', $filePrefix = 'config_') {

        $envConfig = parse_ini_file($pathToConfigFiles . "/$filePrefix$env.ini", true);

        if ($commonFileName != '') {
            $commonConfig = parse_ini_file($pathToConfigFiles . "/$filePrefix$commonFileName.ini", true);
            $this->config = $this->configMerge($commonConfig, $envConfig);
        } else {
            $this->config = $envConfig;
        }
    }

    /**
     * Returns the value of a specific paramater within a specific section of
     * the config file.
     *
     * @param string $section The section (1st level array).
     * @param string $param The name (key) of the parameter to retrieve
     * (2nd level array).
     * @param string $default The value returned if $config[$section][$param]
     * does not exist.
     * @return string The parameter or default value.
     */
    public function get($section, $param, $default = null) {

        if (array_key_exists($section, $this->config) && array_key_exists($param, $this->config[$section])) {
            return $this->config[$section][$param];
        }
        return $default;
    }

    /**
     * Returns the entire config array. For debug / test purposes.
     *
     * @return array
     */
    public function getAll() {
        return $this->config;
    }

    /**
     * Returns a merged config array with updated values. This method is
     * recursive by design.
     *
     * @param array $default The default values.
     * @param array $updated The updated values.
     * @return array A merged array with updated values.
     */
    private function configMerge($default, $updated) {

        // initializing the output
        $finalConfig = array();

        // going through each element of the default array
        foreach ($default as $key => $value) {

            // if the value does not exist in the updated version,
            // we safely use the default version
            if (!array_key_exists($key, $updated)) {
                $finalConfig[$key] = $value;

            } else {
                // using the updated version since it exists
                $finalConfig[$key] = $updated[$key];

                // if current value is an array, we recursively call this function
                if (is_array($value)) {
                    $finalConfig[$key] = $this->configMerge($value, $updated[$key]);
                }
            }
        }

        // final array now contains updated values, not new ones,
        // so we add those
        return array_merge($updated, $finalConfig);
    }

}
