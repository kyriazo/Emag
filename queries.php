<?php
//This query gets a table with entity id body text and summary text
select a.entity_id,body_value,body_summary 
from field_data_field_health_line as a 
join (
    select entity_id,body_value,body_summary 
    from field_data_body as b 
    where bundle='health_line' 
    or bundle='news'
) as c 
on a.entity_id=c.entity_id;

//This query gets a table with entity id image uri and image filename
select filename,uri,entity_id from file_managed as a join (select entity_id,field_image_fid from field_data_field_image where bundle='health_line' or bundle='news') as b on a.fid = b.field_image_fid;


//Entity id, title, created, img_filename, image_uri
select entity_id,title,created,body_value,body_summary,filename,uri 
from file_managed as a 
join (
	select entity_id,field_image_fid 
	from field_data_field_image 
	where bundle='health_line' 
	or bundle='news'
	) as b 
on a.fid = b.field_image_fid
join (
	select nid,title,created 
	from node 
	where type='health_line' 
	or type='news'
	) as c 
on entity_id=c.nid
join (
    select entity_id,body_value,body_summary
    from field_data_body
    where bundle='health_line'
    or bundle='news'
) as d
on entity_id=d.entity_id;



//Gets entity_id,title,created,body_value,body_summary,filename,uri
select b.entity_id,title,created,body_value,body_summary,filename,uri 
from file_managed as a 
join (
	select entity_id,field_image_fid 
	from field_data_field_image 
	where bundle='health_line' 
	or bundle='news'
	) as b 
on a.fid = b.field_image_fid
join (
	select nid,title,created 
	from node 
	where type='health_line' 
	or type='news'
	) as c 
on b.entity_id=c.nid
join (
    select entity_id,body_value,body_summary
    from field_data_body
    where bundle='health_line'
    or bundle='news'
) as d
on b.entity_id=d.entity_id;

?>