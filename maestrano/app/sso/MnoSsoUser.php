<?php

/**
 * Configure App specific behavior for 
 * Maestrano SSO
 */
class MnoSsoUser extends MnoSsoBaseUser
{
  /**
   * Database connection
   * @var PDO
   */
  public $connection = null;
  
  
  /**
   * Extend constructor to inialize app specific objects
   *
   * @param OneLogin_Saml_Response $saml_response
   *   A SamlResponse object from Maestrano containing details
   *   about the user being authenticated
   */
  public function __construct(OneLogin_Saml_Response $saml_response, &$session = array(), $opts = array())
  {
    // Call Parent
    parent::__construct($saml_response,$session);
    
    // Assign new attributes
    $this->connection = $opts['db_connection'];
  }
  
  
  /**
   * Sign the user in the application. 
   * Parent method deals with putting the mno_uid, 
   * mno_session and mno_session_recheck in session.
   *
   * @return boolean whether the user was successfully set in session or not
   */
  protected function setInSession()
  {
    // First set $conn variable (need global variable?)
    $user = Users::findById($this->local_id);
    //var_dump($user->getId());
    
    if ($user && $user->getId()) {
        
      CompanyWebsite::instance()->logUserIn($user, true);
        
      return true;
    } else {
        return false;
    }
  }
  
  
  /**
   * Used by createLocalUserOrDenyAccess to create a local user 
   * based on the sso user.
   * If the method returns null then access is denied
   *
   * @return the ID of the user created, null otherwise
   */
  protected function createLocalUser()
  {
    $lid = null;
    
    if ($this->accessScope() == 'private') {
      // First create the user and get the id
      $user = $this->buildLocalUser();
      $user->save();
      $lid = $user->getId();
      
      // Then create the local contact associated
      // to the user
      if ($lid && $lid > 0) {
        $contact = $this->buildLocalContact($lid);
        $contact->save();
      }
    }
    
    return $lid;
  }
  
  /**
   * Build a local user for creation
   *
   * @return a ProjectPier user object
   */
  protected function buildLocalUser()
  {
    $user = new User();
    $user->setFromAttributes(Array(
      'username' => $this->uid,
      'email'    => $this->email,
      'timezone' => 0,
    ));
    $user->setPassword($this->generatePassword());
    $user->setIsAdmin($this->isUserAdmin());
    $user->setAutoAssign(0);
    
    return $user;
  }
  
  /**
   * Build a local contact for creation
   *
   * @return a ProjectPier contact object
   */
  protected function buildLocalContact($user_id)
  {
    $contact = new Contact();
    $contact->setFromAttributes(Array(
      'display_name' => "$this->name $this->surname",
      'company_id'   => $this->getCompanyIdToAssign(),
      'email'        => $this->email,
      'user_id'      => $user_id,
      'use_gravatar' => 0
    ));
    
    return $contact;
  }
  
  /**
   * Get the id of the company to assign to the
   * user by default
   *
   * @return integer the id of the default company
   */
  protected function getCompanyIdToAssign()
  {
    $result = DB::executeOne("SELECT id FROM pp088_companies ORDER BY id ASC LIMIT 1");
    
    if ($result && $result['id']) {
      return $result['id'];
    }
    
    return 0;
  }
  
  /**
   * Returns wether the user is admin or not
   *
   * @return integer 1 if admin and 0 otherwise
   */
  protected function isUserAdmin() {
    $is_admin = 0; // User
    
    if ($this->app_owner) {
      $is_admin = 1; // Admin
    } else {
      foreach ($this->organizations as $organization) {
        if ($organization['role'] == 'Admin' || $organization['role'] == 'Super Admin') {
          $is_admin = 1;
        } else {
          $is_admin = 0;
        }
      }
    }
    
    return $is_admin;
  }
  
  
  /**
   * Get the ID of a local user via Maestrano UID lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function getLocalIdByUid()
  {
    $result = DB::executeOne("SELECT id FROM pp088_users WHERE mno_uid = ? LIMIT 1", $this->uid);
    
    if ($result && $result['id']) {
      return $result['id'];
    }
    
    return null;
  }
  
  /**
   * Get the ID of a local user via email lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function getLocalIdByEmail()
  {
    $result = DB::executeOne("SELECT id FROM pp088_users WHERE email = ? LIMIT 1", $this->email);
    
    if ($result && $result['id']) {
      return $result['id'];
    }
    
    return null;
  }
  
  /**
   * Set all 'soft' details on the user (like name, surname, email)
   * Implementing this method is optional.
   *
   * @return boolean whether the user was synced or not
   */
   protected function syncLocalDetails()
   {
     if($this->local_id) {
       $upd1 = DB::executeOne("UPDATE pp088_users 
         SET username = ?, 
         email = ?
         WHERE id = ?",$this->uid, $this->email, $this->local_id);
      
       $upd2 = DB::executeOne("UPDATE pp088_contacts 
         SET display_name = ?,
         email = ?
         WHERE user_id = ?","$this->name $this->surname", $this->email, $this->local_id);
      
       return $upd1 && $upd2;
     }
     
     return false;
   }
  
  /**
   * Set the Maestrano UID on a local user via id lookup
   *
   * @return a user ID if found, null otherwise
   */
  protected function setLocalUid()
  {
    if($this->local_id) {
      $upd = DB::executeOne("UPDATE pp088_users 
        SET mno_uid = ?
        WHERE id = ?",$this->uid, $this->local_id);
     
      return $upd;
    }
    
    return false;
  }
}