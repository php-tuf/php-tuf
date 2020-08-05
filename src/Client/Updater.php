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
    $root_data = json_decode(fopen(__DIR__ . '/../../fixtures/tufclient/tufrepo/metadata/current/root.json'));
    $version = (int) $root_data['signed']['version'];
    $next_version = $version + 1;


    return TRUE;
  }
}