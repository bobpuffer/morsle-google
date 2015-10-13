<?php
global $CFG;
require_once("$CFG->dirroot/google/constants.php");
require_once("$CFG->dirroot/google/gauth.php");
require_once("$CFG->dirroot/repository/morsle/lib.php");

/***** COLLECTIONS *****/

/*
* creates a read or a write folder for specified user (course)
* optionally makes it a subcollection of collectionid
*/
function createcollection($morsle, $foldername, $collectionid = null) {
    $folder = new Google_Service_Drive_DriveFile($morsle->client);
    $folder->setTitle($foldername);
    $folder->setMimeType('application/vnd.google-apps.folder');

    // Set the parent folder.
    if ($collectionid !== null) {
        $parent = new Google_Service_Drive_ParentReference();
        $parent->setId($collectionid);
        $folder->setParents(array($parent));
    }

    try {
      $createdFile = $morsle->service->files->insert($folder, array(
        'mimeType' => $mimeType,
      ));
    } catch (Exception $e) {
      print "An error occurred: " . $e->getMessage();    
    }
    if (isset($createdFile->id)) {
        return $createdFile->id;
    }
}

/*
 * returns the collectionid for a collection optionally looking in a collection for the collection
 */
function get_collection($morsle, $title, $collectionid = null) {
    $service_name = 'drive';
    $value = 'title = ' . "'" . $title . "' and trashed = false and mimeType = 'application/vnd.google-apps.folder'";
    if ($collectionid !== null) {
        $value .= "and '$collectionid' in parents ";
    }
    $files = $morsle->service->files->listFiles(array('q' => $value));
    if (sizeof($files['items']) == 0) {
        return false;
    }
    foreach($files['items'] as $item) {
        $id = $item['id'];
    }
    return $id;
}

/*
 * expects $collectionid to be a folderid not name
 * NOT LIKELY TO BE USED ANYMORE
 */
function add_file_tocollection($morsle, $docid, $collectionid) {
    $folder = new Google_Service_Drive_DriveFile($morsle->client);
    $parent = new Google_Service_Drive_ParentReference();
    $parent->setId($collectionid);
    $morsle->service->parents->insert($docid, $parent);
}

/*
* expects $collectionid to be a folderid not name
*/

function delete_file_fromcollection($morsle, $docid, $collectionid) {
    $service_name = 'drive';
    $morsle->get_token($service_name);
    $parent = new Google_Service_Drive_ParentReference();
    $parent->setId($collectionid);
    $morsle->service->parents->delete($docid, $parent);
}

/*
 * strips off any parameters from the url and then gets the docid for what remains
* includes document or spreadsheet by default unless something is sent for docnoinclude
* NOT LIKELY TO BE USED ANYMORE
 */

function strip_id ($docid, $docnoinclude = null) {
	$link = explode('?', $docid);
	$link = explode('/',$link[0]);
	if ($link[sizeof($link)-1] == 'contents'){
		unset($link[sizeof($link)-1]);
	}
	if ($docnoinclude !== null) {
		$pattern = '/[a-z]*%3A/';
		return preg_replace($pattern,'', $link[sizeof($link)-1]);
	}
	return $link[sizeof($link)-1];
}

/**** FILES ****/
/*
 *  transfer file to google and optionally adds to a collection
 *  determines mime type and corresponding icon
 *  uses googles resumable file uploader protocol
 */

function get_doc_feed($morsle, $collectionid, $max = 1000) {
    $drive = new Google_Service_Drive($morsle->client);
    $resource = $drive->files;
    $searchtime = time() - (60*60*24*365*2);
    $date = new DateTime(date('y-m-d', $searchtime));
    $formatdate = $date->format("Y-m-d\TH:i:sP");
    $files = $resource->listFiles(array("q" => "'$collectionid' in parents and trashed = false and modifiedDate > '$formatdate'", 'maxResults' => $max));
    $titles = array();
    foreach ($files->items as $item) {
        $titles[s($item['title'])] = $item;
    }
    return $titles;
}        

function get_doc_id($link) {
    $split = explode('/', $link);
    return $split[7];
}

function get_doc_id_byname($morsle, $name) {
    $drive = new Google_Service_Drive($morsle->client);
    $resource = $drive->files;
    $files = $resource->listFiles(array("q" => 'title = ' . "'$name'" . 'and trashed = false'));
    return $files->items[0]['id'];
}        
        
function send_file_togoogle($morsle, $title, $filetobeuploaded, $mimetype, $collectionid = null) {

  // Now lets try and send the metadata as well using multipart!
    $service = new Google_Service_Drive($morsle->client);
    $file = new Google_Service_Drive_DriveFile();
    $file->setTitle($title);
    $file->setMimeType($mimetype);
    $file->setFileSize(filesize($filetobeuploaded));

      // Set the parent folder.
    if ($collectionid !== null) {
        $parent = new Google_Service_Drive_ParentReference();
        $parent->setId($collectionid);
        $file->setParents(array($parent));
    }
    $result2 = $service->files->insert(
      $file,
      array(
        'data' => file_get_contents($filetobeuploaded),
        'mimeType' => $mimetype,
        'uploadType' => 'multipart',
        'convert' => 'true'
      )
  );
}

/*
 * CALENDAR FUNCTIONS
 */

/*
 * PERMISSIONS FUNCTIONS
 */

/**
 * Insert a new permission.
 *
 * @param String $fileId ID of the file to insert permission for.
 * @param String $value User or group e-mail address, domain name or NULL for
                       "default" type.
 * @param String $type The value "user", "group", "domain" or "default".
 * @param String $role The value "owner", "writer" or "reader".
 * @return Google_Servie_Drive_Permission The inserted permission. NULL is
 *     returned if an API error occurred.
 */
function assign_permissions($morsle, $fileId, $value = array(), $role = array(), $type = 'user') {
    $service = new Google_Service_Drive($morsle->client);
    foreach ($value as $id => $share) {
        $newPermission = new Google_Service_Drive_Permission();
        $newPermission->setValue($share);
        $newPermission->setType($type);
        $newPermission->setRole($role[$id]);
        try {
          $service->permissions->insert($fileId, $newPermission, array('sendNotificationEmails' => 0));
        } catch (Exception $e) {
          print "An error occurred: " . $e->getMessage();
        }
    }
    return NULL;
}
    
    
 /*
 * SPREADSHEET FUNCTIONS
 */

/*
 *
 */
function copy_spreadsheet($morsle, $title, $template, $collectionid = null) {
    $service = new Google_Service_Drive($morsle->client);
    $file = new Google_Service_Drive_DriveFile();
    $file->setTitle($title);

    // Set the parent folder.
    if ($collectionid !== null) {
        $parent = new Google_Service_Drive_ParentReference();
        $parent->setId($collectionid);
        $file->setParents(array($parent));
    }
    $result2 = $service->files->copy(
      $template,
      $file
    );
    return $result2->id;
}

function get_worksheets($ce, $id) {
    $url = "https://spreadsheets.google.com/feeds/worksheets/$id/private/full";
    $method = 'GET';
    $headers = ["Authorization" => "Bearer {$ce->auth->access_token}"];
    $req = new Google_Http_Request($url, $method, $headers);
    $curl = new Google_IO_Curl($ce->client);
    $results = $curl->executeRequest($req);
//    echo "$results[2]\n\n";
//    echo "$results[0]\n";
    return simplexml_load_string($results[0]);
}

/*
 * TODO: can we combine the two get_href functions?
 */

function get_href_noentry($feed, $rel) {
    foreach($feed->link as $link) {
        if ($link['rel'] == $rel) {
            return $link['href'];
        }
    }
    return false;
}

/*
 * TODO: can we combine the two get_href functions?
 */

function get_href($feed, $rel) {
    foreach ($feed->entry as $entry) {
        foreach($entry->link as $link) {
            if ($link['rel'] == $rel) {
                return $link['href'];
            }
        }
    }
    return false;
}

/*
 * GET SHEET INFORMATION FUNCTIONS (ID, FEEDS, KEY, LINK, ETC)
 */

function get_sheetid($feed, $title) {
	foreach ($feed->entry as $entry) {
		if ($entry->title == $title) {
			$temp = substr($entry->id,strrpos($entry->id,'/')+1,10);
			return $temp;
		}
	}
}

function get_sheet_key($sheet, $delim = '%3A') {
	$rawtemp = substr($sheet,strpos($sheet,$delim)+strlen($delim),100);
	$links = explode('?',$rawtemp);
	return $links[0]; // id for raw data file for ce-report
}

function get_cell_contents($base_feed, $owner, $cells) {
	$contents = array();
	$params = array('xoauth_requestor_id' => $owner, 'min-row' => $cells[0], 'max-row' => $cells[1], 'min-col' => $cells[2], 'max-col' => $cells[3]);
//    $contenttype = 'application/x-www-form-urlencoded';
    $query  = twolegged($base_feed, $params, 'GET', null, '3.0', null);
    if (!$query->info['http_code'] == 200) {
    	error('Got bad return from Google sheet query');
    } else {
		$feed = simplexml_load_string($query->response);
		// this works because if the query comes back empty for the search there is no entry element
    }
	foreach ($feed->entry as $cell) {
		$contents[s($cell->title)] = s($cell->content);
	}
	return $contents;
}


/*
 *  function get_sheet_link
 *  parameter $owner: the account from which to do the query
 *  parameter $title: document to look for
 *  TODO: how is this used and could we have a conflict with the null parameters?
 *  parameter $rel (optional) the link "rel" identifier in the returned xml if we want a link returned
 */
function get_sheet_link($morsle, $title, $parents = array(), $max = 1000) {
    $drive = new Google_Service_Drive($morsle->client);
    $resource = $drive->files;
    $query = array('q' => "'title' contains '$title'", 'maxResults' => $max);
    foreach ($parents as $parent) {
        $query[] = 'parent => $parent';
    }
    $files = $resource->listFiles($query);
    
    $titles = array();
    foreach ($files->items as $item) {
        $titles[s($item['title'])] = $item;
    }
    return $titles;
}

/*
 * UPDATE CELL CONTENTS FUNCTIONS
 */

function update_import_cell($base_feed, $owner, $key, $sheetid, $row, $col, $formula, $celledit) {
	$updatedata = '<?xml version=\'1.0\' encoding=\'UTF-8\'?>
                        <entry xmlns="http://www.w3.org/2005/Atom" xmlns:docs="http://schemas.google.com/docs/2007" xmlns:gs="http://schemas.google.com/spreadsheets/2006">
                        <id>https://spreadsheets.google.com/feeds/cells/' . $key . '/' . $sheetid . '/private/full/R' . $row . 'C' . $col . '</id>
                        <link rel="edit" type="application/atom+xml"
                        href="' . $celledit . '"/>
                        <gs:cell row="' . $row . '" col="' . $col . '" inputValue="' . $formula . '"/>
                        </entry>';
	$params = array('xoauth_requestor_id' => $owner);
	return twolegged($base_feed, $params, 'PUT', $updatedata, '3.0');
}


function cell_post($url, $id, $row, $col, $value) {
	return '<entry>
	    <id>' . $url . '</id>
	    <link rel="edit" type="application/atom+xml"
	    	href="' . $url . '"/>
	    <gs:cell row="' . s($row) . '" col="' . s($col) . '" inputValue="' . $value . '"/>
		</entry>';
}

function batchdocpermpost($email, $role, $action, $base_feed) {
	$id = $base_feed . '/' . urlencode('user:' . $email);
	switch ($action) {
		case 'add':
			return '<entry>
			<batch:id>' . $id . '</batch:id>
			<batch:operation type="insert"/>
			<gAcl:role value="' . $role . '"/>
			<gAcl:scope type="user" value="' . $email . '"/>
			</entry>';
		case 'delete':
			return '<entry>
			<id>' . $id . '</id>
			<batch:operation type="delete"/>
			<gAcl:role value="' . $role . '"/>
			<gAcl:scope type="user" value="' . $email . '"/>
			</entry>';
		case 'update':
			return '<entry>
			<id>' . $id . '</id>
			<batch:operation type="update"/>
			<gAcl:role value="' . $role . '"/>
			<gAcl:scope type="user" value="' . $email . '"/>
			</entry>';
	}
}
?>