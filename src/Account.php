<?php
/**
 * SQRL
 *
 * Copyright (c) 2014-2015 Gary Marriott & Jürgen Haas
 *
 * Description
 *
 * Licence     GNU LGPL V3
 *
 * @package    SQRL
 * @author     Jürgen Haas <juergen@paragon-es.de>
 * @author     Gary Marriott <ramriot@gmail.com>
 * @copyright  2014-2015 Gary Marriott & Jürgen Haas
 * @license    http://opensource.org/licenses/LGPL-3.0
 * @link       https://github.com/GMJH/SQRL-PHP-Library
 */

namespace GMJH\SQRL;

/**
 *
 */
abstract class Account {

  protected $account;

  /**
   * @param $account
   *  The account object can be any type (e.g. integer for a user id or stdClass
   *  for a fully loaded user object) and the implementing instance needs to
   *  make sure that all methods can deal with the given object.
   */
  public function __construct($account) {
    $this->account = $account;
  }

  #region Main (potential overwrite) ===========================================

  /**
   * @return bool
   */
  public function enabled() {
    return TRUE;
  }

  /**
   * @return bool
   */
  public function logged_in() {
    return FALSE;
  }

  /**
   * @param $account
   * @return bool
   */
  public function equals($account) {
    if (empty($account)) {
      return TRUE;
    }
    return $this->compare($account);
  }

  /**
   * @param $account
   * @return bool
   */
  protected function compare($account) {
    return ($this->account == $account);
  }

  #endregion

  #region Abstract methods for implementation ==================================

  /**
   * Callback which is called by sqrl_client() if we get a setkey command from
   * the SQRL client in the context of a link operation.
   *
   * @param Client $client
   * @return bool
   */
  abstract public function command_setkey_link($client);

  /**
   * This verb requests the web server to establish—or update an existing—identity
   * association. The client must provide the user's current identity key (idk),
   * identity signature (ids), server unlock key (suk) and verify unlock key (vuk).
   *
   * If no identity association currently exists for this account, the new values
   * will be accepted. But if an existing (and differing) identity association
   * already exists for the user, the client must also sign the request (the
   * entire query package) with a valid unlock request signature (urs) in order
   * for the web server to accept any modification to the account's existing
   * identity association.
   *
   * @param Client $client
   * @return bool
   */
  abstract function command_setkey($client);

  /**
   * This verb requests the web server to establish—or update the account's
   * existing—identity lock data (consisting of the server unlock key (suk) and
   * verify unlock key (vuk). If those values for the user's account are currently
   * empty, the client's new values will be accepted. If those values are not null,
   * the client must also provide the valid unlock request signature (urs) for the
   * current values to enable their replacement.
   *
   * @param Client $client
   * @return bool
   */
  abstract public function command_setlock($client);

  /**
   * This verb instructs the web server to immediately disable the SQRL system's
   * login privilege for this account. This might be requested if the user had
   * reason to believe that their SQRL master key had been compromised. It is
   * intended to be a short-term stop-gap measure to protect important accounts
   * until a new master key can be created and set. A recognized user may therefore
   * request this without supplying any additional credentials.
   *
   * @param Client $client
   * @return bool
   */
  abstract public function command_disable($client);

  /**
   * This is the reverse of the disable verb. It reestablishes SQRL system login
   * privilege for the user's account. Unlike disable, however, enable requires
   * the additional authorization provided by the account's current unlock request
   * signature (urs).
   *
   * @param Client $client
   * @return bool
   */
  abstract public function command_enable($client);

  /**
   * This verb instructs the web server to completely remove (in one fell swoop)
   * ALL of the SQRL authentication information from the user's account. Everything.
   * This returns the web account to the condition it was in before any SQRL
   * association was ever created. The user would subsequently be free to
   * re-associate themselves with the account, though they would first need to
   * authenticate their identity through non-SQRL means.
   *
   * @param Client $client
   * @return bool
   */
  abstract public function command_delete($client);

  /**
   * There are two variants of logging in with SQRL. The login verb requests the
   * web server to login the session that was pending from the original SQRL link
   * URL. This would be the typical choice in a fully trusted scenario where the
   * user and/or SQRL client was confident that a MITM phishing attack was unlikely.
   *
   * @param Client $client
   * @return bool
   */
  abstract public function command_login($client);

  /**
   * This second logme variant of SQRL login requests the web server to explicitly
   * DISABLE any login based upon the original pending session generated for the
   * original SQRL link URL. Instead, the web server is asked to activate and honor
   * subsequent use of the “higher confidence” URL that was provided by the lnk
   * parameter of an earlier server reply. If no lnk URL was provided, if the
   * “lnk=” URL was received over an insecure HTTP (non-HTTPS) connection, or if
   * that URL was not received early enough to have been returned and validated by
   * the web server (also over a secure HTTPS connection), the logme option is not
   * available.
   *
   * @param Client $client
   * @return bool
   */
  abstract public function command_logme($client);

  /**
   * This verb requests the web server to immediately logoff and invalidate any
   * and all currently logged on session for this user account. For example, if
   * not implied, it might accompany the use of the disable verb to shutdown and
   * protect the user's SQRL access to a web server account. If a web server
   * supported multiple simultaneous logged on sessions per account, this could
   * be used along with the login or logme verbs to foreclose any other existing
   * accounts prior to the user logging in with their SQRL credentials.
   *
   * @param Client $client
   * @return bool
   */
  abstract public function command_logoff($client);

  #endregion

}
