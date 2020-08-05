<?php


namespace Tuf\Client;


class Updater
{

  /**
   * @var string
   */
  protected $repoName;

  /**
   * @var \array[][]
   */
  protected $mirrors;

  /**
   * Updater constructor.
   *
   * @param string $repository_name
   * @param array[][] $mirrors
   *   Re
   */
  public function __construct($repository_name, $mirrors) {
    $this->repoName = $repository_name;
    $this->mirrors = $mirrors;
  }


  /**
   * @todo Update from python comment https://github.com/theupdateframework/tuf/blob/1cf085a360aaad739e1cc62fa19a2ece270bb693/tuf/client/updater.py#L999
   *
   * @param bool $unsafely_update_root_if_necessary
   */
  public function refresh($unsafely_update_root_if_necessary = TRUE) {

  }

  /**
   * Validates a target.
   *
   * @param $target_repo_path
   * @param $target_stream
   *
   * @return bool
   *   Returns true if the target validates.
   */
  public function validateTarget($target_repo_path, $target_stream) {
    $root_data = json_decode($this->getRepoFile('root.json'), TRUE);
    $signed = $root_data['signed'];
    $version = (int) $signed['version'];
    $next_version = $version + 1;
    $next_root_contents = $this->getRepoFile("$next_version.root.json");
    if ($next_root_contents) {
      // @todo 🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥🔥Add steps do root rotation spec steps 1.3 -> 1.7.
      //  Not production ready🙀.
      throw new \Exception("Root rotation not implemented.");

    }
    $expires = $signed['expires'];
    $fake_now = '2020-08-04T02:58:56Z';
    $expire_date = \DateTime::createFromFormat($fake_now);
    $now_date = \DateTime::createFromFormat($expires);
    if ($now_date > $expire_date) {
      throw new \Exception("Root has expired");
    }

    $this->getRepoFile("$version")






    return TRUE;
  }

  private function getRepoFile($string) {
    try {
      return file_get_contents(__DIR__ .  "/../../fixtures/tufclient/tufrepo/metadata/current/$string");
    }
    catch (\Exception $exception) {
      return FALSE;
    }

  }
}