<?php
/************************************************************************
* @Author: MedeeaWeb Works                                              *
************************************************************************/ 
$ft=new ft(ADMIN_PATH.MODULE."templates/");
$ft->define(array('main' => "signup.html"));

//$page_title='Login Member';
//$next_function ='auth-login';
session_start();
$dbu = new mysql_db();

//$dbu->query("select name from cms_menu where menu_id=".$glob['menu_id']);

$ft->assign('CSS_PAGE', 'signup');

if($glob['username']) 
{
	$ft->assign('UNAME', $glob['username']);	
}
if($glob['join_email']) 
{
	$ft->assign('JOIN_MAIL', $glob['join_email']);	
}

$site_meta_title=$meta_title.get_meta($glob['pag'], $glob['lang'], 'title');
$site_meta_keywords=$meta_keywords.get_meta($glob['pag'], $glob['lang'], 'keywords');
$site_meta_description=$meta_description.get_meta($glob['pag'], $glob['lang'], 'description');

$tags = get_template_tag('login', $glob['lang']);
foreach($tags as $name => $row){
  $ft->assign($name, $row);
}

$ft->assign('MESSAGE', get_error($glob['error'],$glob['success']));
$ft->parse('CONTENT','main');
return $ft->fetch('CONTENT');
?>