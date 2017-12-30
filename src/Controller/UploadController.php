<?php

namespace Hunter\safe_upload\Controller;

use Zend\Diactoros\ServerRequest;
use Psr\Http\Message\UploadedFileInterface;
use Zend\Diactoros\Response\JsonResponse;
use Rundiz\Upload\Upload;

/**
 * Class Upload.
 *
 * @package Hunter\safe_upload\Controller
 */
class UploadController {
  /**
   * safe_upload.
   *
   * @return string
   *   Return safe_upload string.
   */
  public function safe_upload(ServerRequest $request) {
    $result = false;
    $allowed_file = array();
    $uploadedFiles = $request->getUploadedFiles();
    $parms = $request->getParsedBody();
    if(empty($parms) || !isset($parms['accept']) || !isset($parms['exts'])){
        return new JsonResponse($result);
    }

    switch ($parms['accept'])
    {
    case 'images':
      if(!empty(array_diff(explode('|', $parms['exts']), array('jpg','png','gif','bmp','jpeg')))){
        return new JsonResponse($result);
      }
      $allowed_file = explode('|', $parms['exts']);
      break;
    case 'file':
      if(!empty(array_diff(explode('|', $parms['exts']), array('doc','pdf','txt','xls','zip','rar','7z','tif','obj','pmd','vmd')))){
        return new JsonResponse($result);
      }
      $allowed_file = explode('|', $parms['exts']);
      break;
    case 'video':
      if(!empty(array_diff(explode('|', $parms['exts']), array('rm','rmvb','wmv','avi','mp4','3gp','mkv')))){
        return new JsonResponse($result);
      }
      $allowed_file = explode('|', $parms['exts']);
      break;
    case 'audio':
      if(!empty(array_diff(explode('|', $parms['exts']), array('wav','mp3','ogg','wma','aac')))){
        return new JsonResponse($result);
      }
      $allowed_file = explode('|', $parms['exts']);
      break;
    default:
      return new JsonResponse($result);
    }

    if (!empty($uploadedFiles)) {
      foreach ($uploadedFiles as $key => $value) {
        if ($value instanceof UploadedFileInterface) {
          $result = $this->safe_upload_file($key, $value, $allowed_file);
        }
      }
    }
    return new JsonResponse($result);
  }

  /**
   * safe_upload.
   *
   * @return string
   *   Return safe_upload string.
   */
  private function safe_upload_file($field_name, $value, $allowed_file = array('gif', 'jpg', 'jpeg', 'png')) {
    global $auto_image_compress;
    $module = '';
    if(empty($module)) {
      $module = substr($field_name,0,strpos($field_name,'-'));
    }

    $result = array();
    $msg = '';
    $Upload = new Upload($field_name);
    $move_dir = 'sites/upload/'.$field_name.'/';
    if(!empty($module) && module_exists($module)){
      $move_dir = 'sites/upload/'.$module.'/'.str_replace("$module-","",$field_name).'/';
    }
    $Upload->move_uploaded_to = $move_dir;
    if (!is_dir($move_dir)){
      mkdir($move_dir, 0755, true);
    }
    // Allowed for gif, jpg, png
    $Upload->allowed_file_extensions = $allowed_file;
    // Max file size is 900KB.
    //$Upload->max_file_size = 900000;
    // You can name the uploaded file to new name or leave this to use its default name. Do not included extension into it.
    $Upload->new_file_name = hunter_rename($value->getClientFilename());
    // Overwrite existing file? true = yes, false = no
    $Upload->overwrite = false;
    // Web safe file name is English, number, dash, underscore.
    $Upload->web_safe_file_name = true;
    // Scan for embedded php or perl language?
    $Upload->security_scan = true;
    // If you upload multiple files, do you want it to be stopped if error occur? (Set to false will skip the error files).
    $Upload->stop_on_failed_upload_multiple = false;

    // Begins upload
    $upload_result = $Upload->upload();
    // Get the uploaded file's data.
    $uploaded_data = $Upload->getUploadedData();

    if (is_array($uploaded_data) && !empty($uploaded_data)) {
        if(module_exists('image_compress') && $auto_image_compress['enable']){
          hunter_compress_image($move_dir.$Upload->new_file_name.'.'.$uploaded_data[0]['extension'], $move_dir.$Upload->new_file_name.'.'.$uploaded_data[0]['extension'], $auto_image_compress['quality']);
        }

        $uploaded_data[0]['full_path_new_name'] = $move_dir.$Upload->new_file_name.'.'.$uploaded_data[0]['extension'];
        $uploaded_data[0]['src'] = $move_dir.$Upload->new_file_name.'.'.$uploaded_data[0]['extension'];
    }

    if ($upload_result === true) {
        $code = 0;
        $msg .= 'success';
    }

    // To check for the errors.
    if (is_array($Upload->error_messages) && !empty($Upload->error_messages)) {
        $code = 1;
        foreach ($Upload->error_messages as $error_message) {
            $msg .= '<p>'.$error_message.'</p>'."\n";
        }
    }

    $result = array(
      'code'=> $code,
      'msg' => $msg,
      'data' => count($uploaded_data) == 1 ? reset($uploaded_data) : $uploaded_data
    );

    return $result;
  }

}
