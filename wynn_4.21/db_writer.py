# db_writer.py

import os
import mysql.connector
import logging
import re # Import re for filename parsing
from datetime import datetime
# Import central configuration
import config

# --- Function to write Gemini Tags to Database ---
# (Keep this function as is - it writes to topics_file)
def write_gemini_tags_to_database():
    """
    Reads the final filtered tags file (Format: Title\tURL\tTag1,Tag2,...)
    and inserts/updates data into the 'topics_file' table.
    """
    module_name = "DB Writer (Gemini Tags)"
    tags_output_dir = config.GEMINI_TAGGING_OUTPUT_DIR
    tags_filename = config.GEMINI_FINAL_OUTPUT_FILENAME
    tags_filepath = os.path.join(tags_output_dir, tags_filename)
    logging.info(f"[{module_name}] Starting DB write for Gemini tags from: {tags_filepath}")
    if not os.path.isfile(tags_filepath):
        logging.error(f"[{module_name}] Tags file not found: '{tags_filepath}'"); return False
    # --- Database connection and insertion logic for topics_file ---
    connection = None; cursor = None; inserted_count, updated_count, skipped_count = 0, 0, 0; success = False
    try:
        logging.info(f"[{module_name}] Connecting to DB '{config.DB_CONFIG.get('database', 'N/A')}'..."); connection = mysql.connector.connect(**config.DB_CONFIG); cursor = connection.cursor()
        logging.info(f"[{module_name}] DB connection successful.")
        sql_query = """
            INSERT INTO topics_file (`Title`, `Link`, `Content`, `Created_Time`) VALUES (%s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE `Title` = VALUES(`Title`), `Content` = VALUES(`Content`), `Created_Time` = VALUES(`Created_Time`); """
        logging.info(f"[{module_name}] Reading tags file: {tags_filepath}")
        with open(tags_filepath, mode='r', encoding='utf-8') as file:
            for line_num, line in enumerate(file, 1):
                if line_num == 1 and "GeneratedTags" in line: logging.debug(f"[{module_name}] Skipping header."); continue
                line = line.strip();
                if not line: logging.debug(f"[{module_name}] Line {line_num}: Skip empty."); skipped_count += 1; continue
                parts = line.split('\t')
                if len(parts) != 3: logging.warning(f"[{module_name}] Line {line_num}: Skip malformed TSV ({len(parts)} fields). Line: '{line[:150]}...'"); skipped_count += 1; continue
                title_for_db, link_for_db, content_for_db = parts[0].strip(), parts[1].strip(), parts[2].strip()
                if not title_for_db: logging.warning(f"[{module_name}] Line {line_num}: Skip empty title. Link: '{link_for_db}'."); skipped_count += 1; continue
                if not link_for_db or not link_for_db.startswith(('http://', 'https://')): logging.warning(f"[{module_name}] Line {line_num}: Skip invalid link: '{link_for_db}'."); skipped_count += 1; continue
                now = datetime.now()
                try:
                    cursor.execute(sql_query, (title_for_db, link_for_db, content_for_db, now))
                    if cursor.rowcount == 1: inserted_count += 1; logging.debug(f"[{module_name}] Inserted: {link_for_db}")
                    elif cursor.rowcount > 1: updated_count += 1; logging.debug(f"[{module_name}] Updated: {link_for_db}")
                except mysql.connector.Error as err: logging.error(f"[{module_name}] Line {line_num}: DB Error for link '{link_for_db}': {err}"); skipped_count += 1
                except Exception as exc: logging.error(f"[{module_name}] Line {line_num}: Unexpected DB error link '{link_for_db}': {exc}", exc_info=True); skipped_count += 1
        rows_affected = inserted_count + updated_count
        if rows_affected > 0: logging.info(f"[{module_name}] Committing {rows_affected} tag changes..."); connection.commit(); logging.info(f"[{module_name}] Commit successful."); success = True
        else: logging.info(f"[{module_name}] No tag rows inserted/updated."); success = (skipped_count == 0 or inserted_count == 0 and updated_count == 0)
        if skipped_count > 0: logging.warning(f"[{module_name}] {skipped_count} tag lines skipped."); success = success and (inserted_count > 0 or updated_count > 0) # Overall success requires some changes if skips occurred
    except mysql.connector.Error as err: logging.error(f"[{module_name}] DB connection/op error: {err}"); success = False
    except FileNotFoundError: logging.error(f"[{module_name}] Tags file disappeared: '{tags_filepath}'"); success = False
    except Exception as e: logging.error(f"[{module_name}] Unexpected tags DB error: {e}", exc_info=True); success = False
    finally:
        if cursor: 
            try: cursor.close(); 
            except Exception: pass
        if connection and connection.is_connected():
            if not success and connection.in_transaction: 
                try: logging.info(f"[{module_name}] Rolling back."); connection.rollback(); 
                except Exception: pass
            try: connection.close(); logging.info(f"[{module_name}] DB Connection closed."); 
            except Exception: pass
    logging.info(f"[{module_name}] Gemini tags DB write finished. Success: {success}"); return success


# --- NEW Function to write Topic Reports to Database ---
def write_topic_reports_to_database():
    """
    Reads individual topic report files generated by report_generator.py
    (e.g., report_topic_*.txt), extracts the topic, and inserts each report
    into the 'report_file' table with a common Batch_Identifier.
    """
    module_name = "DB Writer (Topic Reports)"

    # --- Define input directory for reports ---
    # Assumes report_generator saves to a 'Topic_Reports' subdir
    report_input_base_dir = config.GEMINI_TAGGING_OUTPUT_DIR
    report_input_subdir = "Topic_Reports"
    report_dir_path = os.path.join(report_input_base_dir, report_input_subdir)
    # --- End Input Path ---

    logging.info(f"[{module_name}] Starting DB write for topic reports from: {report_dir_path}")

    if not os.path.isdir(report_dir_path):
        logging.error(f"[{module_name}] Report directory not found: '{report_dir_path}'. Did report_generator run?")
        return False

    # List report files
    try:
        report_files = [f for f in os.listdir(report_dir_path) if f.startswith("report_topic_") and f.endswith(".txt")]
        if not report_files:
            logging.warning(f"[{module_name}] No report files found in '{report_dir_path}'. Nothing to write.")
            return True # Not an error if no reports were generated
        logging.info(f"[{module_name}] Found {len(report_files)} topic report files.")
    except Exception as e:
        logging.error(f"[{module_name}] Error listing files in '{report_dir_path}': {e}")
        return False

    connection = None
    cursor = None
    inserted_count = 0
    skipped_count = 0
    success_overall = True # Assume success unless an error occurs

    # Generate a Batch ID for this run
    batch_run_id = datetime.now().strftime('%Y%m%d%H%M%S%f')
    logging.info(f"[{module_name}] Using Batch ID for this run: {batch_run_id}")

    try:
        logging.info(f"[{module_name}] Connecting to database '{config.DB_CONFIG.get('database', 'N/A')}'...")
        connection = mysql.connector.connect(**config.DB_CONFIG)
        cursor = connection.cursor()
        logging.info(f"[{module_name}] Database connection successful.")

        # SQL query for the modified report_file table
        sql_query = """
            INSERT INTO report_file (Generated_Time, Topic, Batch_Identifier, Report_Content)
            VALUES (%s, %s, %s, %s)
        """ # Report_ID is auto-increment

        for filename in report_files:
            filepath = os.path.join(report_dir_path, filename)
            topic_name = "Unknown Topic" # Default
            report_content = ""
            now = datetime.now()

            # --- Extract Topic from Filename ---
            match = re.match(r"report_topic_(.+)\.txt$", filename)
            if match:
                # The extracted part might have underscores from sanitization
                topic_name = match.group(1)
                # Optional: Replace underscores back to spaces if needed for display consistency?
                # topic_name = topic_name.replace('_', ' ')
            else:
                logging.warning(f"[{module_name}] Could not parse topic name from filename: '{filename}'. Using default.")
            # --- End Topic Extraction ---

            try:
                # --- Read Report Content ---
                with open(filepath, 'r', encoding='utf-8') as f:
                    report_content = f.read()
                logging.debug(f"[{module_name}] Read content for topic '{topic_name}' ({len(report_content)} chars).")
                # --- End Read Content ---

                if not report_content.strip():
                    logging.warning(f"[{module_name}] Report file for topic '{topic_name}' is empty. Skipping DB insert.")
                    skipped_count += 1
                    continue # Skip this file

                # --- Execute Insert ---
                cursor.execute(sql_query, (now, topic_name, batch_run_id, report_content))
                if cursor.rowcount == 1:
                    inserted_count += 1
                    logging.info(f"[{module_name}] Inserted report for topic: '{topic_name}'")
                else:
                    # Should not happen for simple INSERT unless something is very wrong
                    logging.error(f"[{module_name}] INSERT for topic '{topic_name}' reported {cursor.rowcount} rows affected (expected 1).")
                    skipped_count += 1
                    success_overall = False # Mark overall run as potentially failed
                # --- End Execute Insert ---

                # Commit after each successful insert
                connection.commit()

            except mysql.connector.Error as insert_err:
                logging.error(f"[{module_name}] DB insert error for report file '{filename}' (Topic: {topic_name}): {insert_err}")
                skipped_count += 1
                success_overall = False # Mark run as failed if any insert fails
                try: connection.rollback() # Rollback this specific insert
                except Exception as rb_err: logging.error(f"Rollback error: {rb_err}")
            except FileNotFoundError:
                 logging.error(f"[{module_name}] Report file disappeared during processing: '{filepath}'")
                 skipped_count += 1
                 success_overall = False
            except Exception as exec_err:
                 logging.error(f"[{module_name}] Unexpected error processing report file '{filename}': {exec_err}", exc_info=True)
                 skipped_count += 1
                 success_overall = False
                 try: connection.rollback()
                 except Exception as rb_err: logging.error(f"Rollback error: {rb_err}")


        # --- Final Logging ---
        if inserted_count > 0:
            logging.info(f"[{module_name}] Finished processing. Successfully inserted {inserted_count} topic reports for Batch ID {batch_run_id}.")
        else:
             logging.info(f"[{module_name}] Finished processing. No topic reports were inserted.")

        if skipped_count > 0:
            logging.warning(f"[{module_name}] Skipped {skipped_count} topic reports due to errors or empty content.")

    except mysql.connector.Error as err:
        logging.error(f"[{module_name}] MySQL connection/operational error for reports: {err}")
        success_overall = False
    except Exception as e:
        logging.error(f"[{module_name}] Unexpected error during topic reports DB write: {e}", exc_info=True)
        success_overall = False
    finally:
        if cursor: 
            try: cursor.close(); 
            except Exception: pass
        if connection and connection.is_connected():
            # No final commit needed here as we commit per insert
            # Rollback might have occurred on error already
            try: connection.close(); logging.info(f"[{module_name}] DB Connection closed."); 
            except Exception: pass

    logging.info(f"[{module_name}] Topic reports database write process finished. Overall Success: {success_overall}")
    return success_overall


# --- Standalone Execution Block ---
if __name__ == "__main__":
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(levelname)-8s - %(name)-12s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )

    logging.info("Running DB Writer module directly...")

    # --- Gemini Tags Write (Keep this part) ---
    logging.info("\n--- Gemini Tags Write (to topics_file) ---")
    tags_output_dir = config.GEMINI_TAGGING_OUTPUT_DIR
    tags_input_filename = config.GEMINI_FINAL_OUTPUT_FILENAME
    tags_file = os.path.join(tags_output_dir, tags_input_filename)

    if not os.path.isfile(tags_file):
         logging.error(f"Required input file for tags '{tags_file}' not found.")
    elif not config.DB_CONFIG:
         logging.error("DB_CONFIG not defined in config.py for tags test.")
    else:
        tags_success = write_gemini_tags_to_database()
        if tags_success: logging.info("Gemini Tags write test finished successfully.")
        else: logging.error("Gemini Tags write test finished with errors.")

    # --- Topic Reports Write (NEW part) ---
    logging.info("\n--- Topic Reports Write (to report_file) ---")
    report_input_dir = os.path.join(config.GEMINI_TAGGING_OUTPUT_DIR, "Topic_Reports") # Define expected input dir

    if not os.path.isdir(report_input_dir):
         logging.warning(f"Input directory for topic reports '{report_input_dir}' not found. Skipping report writing.")
         # Don't treat missing dir as error if report generation might be optional
    elif not config.DB_CONFIG:
         logging.error("DB_CONFIG is not defined in config.py for reports test.")
    else:
        # Call the NEW function
        reports_success = write_topic_reports_to_database()
        if reports_success: logging.info("Topic Reports write test finished successfully.")
        else: logging.error("Topic Reports write test finished with errors.")


    logging.info("\nDB Writer module direct execution finished.")