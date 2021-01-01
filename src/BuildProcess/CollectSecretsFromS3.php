<?php

namespace Laravel\VaporCli\BuildProcess;

use Aws\S3\S3Client;
use Laravel\VaporCli\Helpers;

class  CollectSecretsFromS3
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

        $secrets = $this->fetchSecrets($this->parseSecrets(getcwd() . "/{$this->environmentType}-deploy-s3.env"));

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

        echo "Building [{$appName}] [{$envType}] environment." . PHP_EOL;

        $s3Client = new S3Client($args = [
            "version" => "latest",
            "credentials" => [
                "key" => $deployVars['S3_SECRETS_KEY'],
                "secret" => $deployVars['S3_SECRETS_SECRET'],
            ],
            "region" => $deployVars['S3_SECRETS_REGION'],
            "bucket" => $deployVars['S3_SECRETS_BUCKET'],
        ]);

        $envFiles = [
            "$envType-common-secrets.env" => __DIR__ . "/$envType-common-secrets.env",
            "$envType-common-vars.env" => __DIR__ . "/$envType-common-vars.env",
            "$envType-$appName-secrets.env" => __DIR__ . "/$envType-$appName-secrets.env",
            "$envType-$appName-vars.env" => __DIR__ . "/$envType-$appName-vars.env",
            "emr-direct-client-cert.pem" => $this->appPath . "/emr-direct-client-cert.pem",
            "emr-direct-server-cert.pem" => $this->appPath . "/emr-direct-server-cert.pem",
        ];

        $secrets = [];

        foreach ($envFiles as $s3Key => $localPath) {
            echo "Fetching [{$s3Key}] from S3." . PHP_EOL;

            $s3Client->getObject([
                'Bucket' => $args['bucket'],
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
}
