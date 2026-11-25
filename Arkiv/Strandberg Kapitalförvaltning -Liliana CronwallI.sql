Strandberg Kapitalförvaltning postmeta ID 
Liliana Cronwall user_id = 17,  

wp_usermeta umeta_id = 437 till 454 (18 rader)
wp_posts ID i=262 och 264  (logga och profilbild)
wp_posts post_author = 17  absence_reason replacer och "guest Mikael Sjöberg"
wp_postmeta post_ID i=262 och 264  (logga och profilbild)
wp_postmeta post_ID i=967  (guest Mikael Sjöberg)

SELECT * FROM wp_usermeta WHERE user_id = 17;

SELECT * FROM wp_posts WHERE id = 262; 

SELECT * FROM wp_posts WHERE id = 264;

SELECT * FROM wp_postmeta WHERE post_id = 262;

SELECT * FROM wp_postmeta WHERE post_id = 264;

SELECT * FROM wp_postmeta WHERE Post_id = 264;

SELECT * FROM wp_posts WHERE post_author = 17;

SELECT * FROM wp_postmeta WHERE Post_id = 967;