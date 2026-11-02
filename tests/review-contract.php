<?php
$root=dirname(__DIR__);
$slug='vantigo-share-shop-cart-for-woocommerce'; $checked=0; $translated=0;
function review_expect($value,$label) { if(!$value) throw new RuntimeException($label); ++$GLOBALS['checked']; }
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($it as $file) {
    if($file->getExtension()!=='php') continue;
    $relative=str_replace('\\','/',substr($file->getPathname(),strlen($root)+1));
    if($relative!==$slug.'.php'&&strpos($relative,'includes/')!==0&&strpos($relative,'languages/')!==0) continue;
    $code=file_get_contents($file->getPathname());
    review_expect(!preg_match('/^\s*class\s+(?!VANTSHSH_)[A-Za-z_]/m',$code),'Unprefixed class: '.$file);
    review_expect(!preg_match('/^function\s+(?!vantshsh_)[A-Za-z_]/m',$code),'Unprefixed global function: '.$file);
    preg_match_all('/define\(\s*[\'"]([^\'"]+)/',$code,$defines);
    foreach($defines[1] as $define) review_expect(strpos($define,'VANTSHSH_')===0,'Unprefixed constant: '.$define);
    preg_match_all('/add_shortcode\(\s*[\'"]([^\'"]+)/',$code,$tags);
    foreach($tags[1] as $tag) review_expect(strpos($tag,'vantshsh_')===0,'Generic shortcode: '.$tag);
    preg_match_all('/(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*\'(?:\\\\.|[^\'\\\\])*\'\s*,\s*\'([^\']+)\'/s',$code,$domains);
    foreach($domains[1] as $domain) { review_expect($domain===$slug,'Mismatched translation domain: '.$file); ++$translated; }
    preg_match_all('/(?:update_option|add_option|delete_option)\(\s*\'([^\']+)\'/',$code,$options);
    foreach($options[1] as $option) review_expect(strpos($option,'vantshsh_')===0,'Generic option write: '.$option);
}
review_expect($translated>=200,'Translation scan must cover the complete plugin');
$header=file_get_contents($root.'/'.$slug.'.php');
$readme=file_get_contents($root.'/readme.txt');
review_expect(strpos($header,'Text Domain: '.$slug)!==false,'Plugin header text domain');
review_expect(strpos($header,'Version: 1.0.3')!==false&&strpos($readme,'Stable tag: 1.0.3')!==false,'Versions must match');
foreach(array('admin','frontend') as $area) review_expect(file_get_contents($root.'/assets/js/vantshsh-'.$area.'.js')===file_get_contents($root.'/assets/js/vantshsh-'.$area.'.min.js'),'Debug/production JS mismatch: '.$area);
echo "PASS: $checked review-contract assertions; $translated translation references match $slug.\n";
