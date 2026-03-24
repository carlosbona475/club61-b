-- DEPRECATED: esquema antigo (INT). Use ../sql/followers_table.sql (UUID + auth.users) no Supabase.

CREATE TABLE followers (
    follower_id INT NOT NULL,
    followed_id INT NOT NULL,
    PRIMARY KEY (follower_id, followed_id)
);