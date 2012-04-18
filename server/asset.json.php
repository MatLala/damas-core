<?php
/**
 * Author Remy Lalanne
 * Copyright (c) 2005-2011 Remy Lalanne
 */
session_start();
header('Content-type: application/json');

include_once "service1.php"; //error_code()
include_once "../php/DAM.php";
include_once "App/lib.user.php";

include_once "FileVersion/lib.asset.php";
include_once "FileSystem/lib.file.php";

$cmd = arg("cmd");
$ret = false;
$nav = false;

$init = null;
$init = damas_service::init();
if ( !is_null($init) ) {
	header("HTTP/1.1: ".$init);
	echo $init;
	exit;
}

if (!$cmd ) {
	header("HTTP/1.1: 400 Bad Request");
	echo "Bad command";
	exit;
}

if ( !accessGranted() ) {
	header("HTTP/1.1: 403 Forbidden");
	echo "User authentification required";
	exit;
}

if (!allowed("asset::".$cmd)) {
	header("HTTP/1.1: 405 Method Not Allowed");
	echo "Permission denied";
	exit;
}

switch( $cmd )
{
	case "getElementById":
		$id = model::searchKey('id', arg('id'));
		$id = $id[0];
		$ret = model_json::node($id, 1, $NODE_TAG | $NODE_PRM);
		if( !$ret ) {
			header('HTTP/1.1: 404 Not Found');
			echo "Node not found";
			exit;
			//$err = $ERR_NODE_ID;
		}
		break;
	case "time":
		if( ! model::setKey( arg("id"), "time", time() ) ) {
			header("HTTP/1.1: 304 Not Modified Error on update");
			echo "Node update failed";
			exit;
			//$err = $ERR_NODE_UPDATE;
		}
		$ret = true;
		break;
	case "write":
		$ret = DAM::write( arg("id"), arg("text") );
		if (!$ret)
			$err = $ERR_NODE_CREATE;
		break;
	case "lock":
		if( model::hastag( arg("id"), "lock" ) )
		{
			$err = $ERR_NODE_UPDATE;
			break;
		}
		$ret = model::tag( arg("id"), 'lock' );
		$ret &= model::setKey( arg("id"), "lock_user", getUser() );
		$ret &= model::setKey( arg("id"), 'lock_text', arg("comment") );
		if (!$ret)
			$err = $ERR_ASSET_LOCK;
		break;
	case "unlock":
		if( asset_ismylock( arg("id") ) )
		{
			$ret = model::untag( arg("id"), 'lock' );
			model::removeKey( arg("id"), 'lock_user' );
			model::removeKey( arg("id"), 'lock_text' );
		}
		if (!$ret)
			$err = $ERR_NODE_UPDATE;
		break;
	case "upload_set_image":
		if( is_uploaded_file( $_FILES['file']['tmp_name'] ) )
		{
			$extension = pathinfo( $_FILES['file']['name'], PATHINFO_EXTENSION );
			if( move_uploaded_file( $_FILES['file']['tmp_name'], $assetsLCL . '/.damas/images/' . arg("id") . '.' . $extension ) )
			{
				model::setKey( arg("id"), 'image', '/.damas/images/' . arg("id") . '.' . $extension );
				break;
			}
		}
		$err = $ERR_ASSET_UPDATE;
		break;
	case "upload_create_asset":
		if( is_uploaded_file( $_FILES['file']['tmp_name'] ) )
		{
			$file = model::getKey( arg( 'id' ), 'dir' ) . '/' . $_FILES['file']['name'];
			if( move_uploaded_file( $_FILES['file']['tmp_name'], $assetsLCL . $file ) )
			{
				$id = model::createNode( arg( 'id' ), "asset" );
				model::setKey( $id, 'file', $file );
				model::setKey( $id, 'text', arg( 'message' ) );
				model::setKey( $id, 'user', getUser() );
				model::setKey( $id, 'time', time() );
				$ret = model_xml::node( $id, 1, $NODE_TAG | $NODE_PRM );
				break;
			}
		}
		$err = $ERR_NODE_CREATE;
		$ret = false;
		break;
	case "upload":
		if( model::hastag( arg( 'id' ), 'lock' ) )
		{
			if( model::getKey( arg( 'id' ), 'lock_user' ) != getUser() )
			{
				$err = $ERR_ASSET_UPDATE;
				echo sprintf( "<error>asset is locked for the user %s</error>",
					model::getKey( arg( 'id' ), 'lock_user' )
				);
				$ret = false;
				break;
			}
		}
		$path = $_FILES['file']['tmp_name'];
		if( !is_uploaded_file( $path ) )
		{
			$err = $ERR_ASSET_UPDATE;
			echo "<error>is_uploaded_file() error</error>";
			$ret = false;
			break;
		}
		if( !assets::asset_upload( arg("id"), $path, arg("message") ) )
		{
			$err = $ERR_ASSET_UPDATE;
			$ret = false;
			echo sprintf( "<error>Permission denied to copy %s to %s</error>",
				$path,
				$assetsLCL . model::getKey( arg( 'id' ), 'file' )
			);
		}
		break;
	case "version_backup":
		$id = assets::version_backup( arg("id") );
		if( !$id ) $err = $ERR_NODE_CREATE;
		if( $id )
			$ret = model_xml::node( $id, 1, $NODE_TAG | $NODE_PRM );
		else
			$ret = false;
		break;
	case "version_increment":
		$ret = assets::version_increment( arg("id"), arg("message") );
		if (!$ret)
			$err = $ERR_ASSET_UPDATE;
		break;
	case "version_increment2":
		if( is_null( arg('id') ) || is_null( arg('message') ) ){
			$err = $ERR_COMMAND; break;
		}
		$ret = assets::version_increment2( arg("id"), arg("message") );
		if (!$ret)
			$err = $ERR_ASSET_UPDATE;
		break;
	case "recycle":
		$ret = DAM::recycle( arg('id') );
		if (!$ret)
			$err = $ERR_NODE_MOVE;
		break;
	case "empty_trashcan":
		$ret = DAM::empty_trashcan();
		if (!$ret)
			$err = $ERR_NODE_ID;
		break;
	//
	// OLD
	//
	case "save":
		$ret = asset_save(arg("id"), arg("path"), arg("comment"));
		if (!$ret)
			$err = $ERR_ASSET_UPDATE;
		break;
	case "saveable":
		if(!is_writable($assetsLCL.model::getKey(arg("id"),'path_backups'))){
			$err = $ERR_FILE_PERMISSION;
			break;
		}
		$ret = asset_saveable(arg("id"));
		if(!$ret)
			$err = $ERR_ASSET_SAVEABLE;
		break;
	case "backup":
		$ret = asset_backup(arg("id"));
		if(!$ret)
			$err = $ERR_ASSET_BACKUP;
		break;
	case "backupundo":
		$ret = asset_backup_undo(arg("id"));
		if(!$ret)
			$err = $ERR_ASSET_UNDOBACKUP;
		break;
	case "commitnode":
		$ret = asset_commit_node(arg("id"),arg("comment"));
		if(!$ret)
			$err = $ERR_ASSET_UPDATE;
		break;
	case "rollback":
		$ret = asset_rollback(arg("id"), arg("comment"));
		echo "ret=".$ret;
		if(!$ret)
			$err = $ERR_ASSET_ROLLBACK;
		break;
	case "savecheck":
		if (!asset_backup_able(arg("id")))
			$err = $ERR_ASSET_READONLY;
		break;
	case "filecheck":
		if( !file_exists( $assetsLCL . model::getKey( arg("id"), 'file' ) ) )
		{
			$err = $ERR_FILE_NOT_FOUND;
			break;
		}
		if( !is_readable( $assetsLCL . model::getKey( arg("id"), 'file' ) ) )
		{
			$err = $ERR_FILE_PERMISSION;
			break;
		}
		if( !model::getKey( arg("id"), "sha1" ) )
		{
			$err = $ERR_ASSET_NOSHA1;
			break;
		}
		if( !asset_check_sha1( arg("id") ) )
			$err = $ERR_ASSET_FILECHECK;
		break;
	default:
		$err = $ERR_COMMAND;
}

damas_service::log_event();

echo soaplike_head($cmd,$err);
echo "\t<FILES";
foreach ($_FILES as $k => $v)
	echo " " . $k.'="'.$v.'"';

?>
