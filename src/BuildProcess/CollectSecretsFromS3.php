<?php

namespace Laravel\VaporCli\BuildProcess;

use Aws\S3\S3Client;
use Laravel\VaporCli\Helpers;

class CollectSecretsFromS3
{
    use ParticipatesInBuildProcess;

    /**
     * Execute the build process step.
     *
     * @return void
     */
    public function __invoke()
    {
        Helpers::step('<options=bold>Collecting Secrets From S3</>');

        $secrets = $this->fetchSecrets(
            $this->parseSecrets(
                $this->s3credentialsPath()
            )
        );

        $this->files->put(
            $this->appPath . '/vaporSecrets.php',
            '<?php return ' . var_export($secrets, true) . ';'
        );
    }

    public function fetchSecrets(array $deployVars)
    {
        if (empty($deployVars)) return [];

        $envType = $deployVars['ENV_TYPE'];
        $appName = $deployVars['APP_NAME'];
        $bucket = $deployVars['S3_SECRETS_BUCKET'];

        echo "Building [{$appName}] [{$envType}] environment." . PHP_EOL;

        $client = new S3Client([
            "version" => "latest",
            "credentials" => [
                "key" => $deployVars['S3_SECRETS_KEY'],
                "secret" => $deployVars['S3_SECRETS_SECRET'],
            ],
            "region" => $deployVars['S3_SECRETS_REGION'],
            "bucket" => $bucket,
        ]);

        $secrets = [];

        foreach ($this->s3FileMapPath($appName, $envType, $this->appPath) as $tuple) {
            $s3Key = $tuple['s3'];
            $localPath = $tuple['local'];

            echo "Fetching [{$s3Key}] from S3." . PHP_EOL;

            $client->getObject([
                'Bucket' => $bucket,
                'Key' => $s3Key,
                'SaveAs' => $localPath,
            ]);

            if (!$this->endsWith($s3Key, '.env')) {
                continue;
            }

            foreach (self::parseSecrets($localPath) as $name => $value) {
                echo "Fetched secret [{$name}]." . PHP_EOL;

                $secrets[$name] = $value;
            }
        }

        return $secrets;
    }

    private function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if (!$length) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }

    private function parseSecrets(string $localPath): array
    {
        if (!file_exists($localPath)) {
            return [];
        }

        return collect(file($localPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->mapWithKeys(function ($line) {
                if (strpos(trim($line), '#') === 0) {
                    return null;
                }

                [$name, $value] = explode('=', $line, 2);

                return [
                    $this->sanitize($name) => $this->sanitize($value)
                ];
            })->filter()->all();
    }

    private function sanitize(string $value)
    {
        return str_replace("'", '', str_replace('"', '', trim($value)));
    }

    /**
     * The path where vapor-cli expects to find Deploy S3 credentials.
     *
     * @return string
     */
    private function s3credentialsPath()
    {
        return getcwd() . "/{$this->environmentType}-deploy-s3.env";
    }

    private function s3FileMapPath(string $appName, string $envType, string $vaporBuildAppPath):array
    {
        return json_decode(
            str_replace('__DIR__', __DIR__,
                str_replace('$vaporBuildAppPath', $vaporBuildAppPath,
                    str_replace('$envType', $envType,
                        str_replace('$appName', $appName,
                            file_get_contents(getcwd() . "/deploy-file-path-map.json"))
                    )
                )
            ),
            true
        );
    }
}
