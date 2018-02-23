--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner:
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = public, pg_catalog;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: keep_instance; Type: TABLE; Schema: public; Owner: moodleadmin; Tablespace:
--

CREATE TABLE keep_instance (
    id integer NOT NULL,
    instance character varying(255),
    notes text
);


ALTER TABLE public.keep_instance OWNER TO moodleadmin;

--
-- Name: keep_instances_id_seq; Type: SEQUENCE; Schema: public; Owner: moodleadmin
--

CREATE SEQUENCE keep_instances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.keep_instances_id_seq OWNER TO moodleadmin;

--
-- Name: keep_instances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: moodleadmin
--

ALTER SEQUENCE keep_instances_id_seq OWNED BY keep_instance.id;


--
-- Name: id; Type: DEFAULT; Schema: public; Owner: moodleadmin
--

ALTER TABLE ONLY keep_instance ALTER COLUMN id SET DEFAULT nextval('keep_instances_id_seq'::regclass);


--
-- Name: keep_instances_instance_key; Type: CONSTRAINT; Schema: public; Owner: moodleadmin; Tablespace:
--

ALTER TABLE ONLY keep_instance
    ADD CONSTRAINT keep_instances_instance_key UNIQUE (instance);


--
-- Name: keep_instances_pkey; Type: CONSTRAINT; Schema: public; Owner: moodleadmin; Tablespace:
--

ALTER TABLE ONLY keep_instance
    ADD CONSTRAINT keep_instances_pkey PRIMARY KEY (id);


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--
