<?php
// Draft Sesotho text requires review by a qualified local educator/translator.
require_once __DIR__.'/db.php';
if(isset($_GET['lang'])&&is_string($_GET['lang'])&&in_array($_GET['lang'],['en','st'],true))$_SESSION['language']=$_GET['lang'];
function language(): string {return $_SESSION['language']??'en';}
function t(string $key): string {static $strings=null;if($strings===null)$strings=require __DIR__.'/../translations/'.language().'.php';return $strings[$key]??$key;}
function question_text(string $english): string {static $questions=null;if(language()!=='st')return $english;if($questions===null)$questions=require __DIR__.'/../translations/questions_st.php';return $questions[$english]??$english;}
function language_url(): string {$query=array_filter($_GET,static fn($value)=>is_string($value));$query['lang']=language()==='en'?'st':'en';return '?'.http_build_query($query);}
