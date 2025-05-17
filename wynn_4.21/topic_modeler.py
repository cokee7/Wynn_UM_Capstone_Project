# topic_modeler.py

import os
import re
import logging
import time
import google.generativeai as genai # Use Gemini
from tqdm import tqdm
import sys
import shutil
import collections

# Import centralized configuration
import config

# --- Helper Functions ---

def configure_gemini():
    """Configures the Gemini API client using settings from config.py."""
    # Use API Key from config (read from environment variable)
    if not config.GOOGLE_API_KEY:
        logging.error("GOOGLE_API_KEY environment variable is not set in config or environment. Cannot configure Gemini.")
        return None
    try:
        genai.configure(api_key=config.GOOGLE_API_KEY)
        logging.info("Gemini API configured successfully.")
        return genai
    except Exception as e:
        logging.error(f"Error configuring Gemini API: {e}", exc_info=True)
        return None

def generate_tags_with_gemini(client, content):
    """
    Sends content to Gemini API, handles retries, returns tags or error marker.
    Uses settings from config.py.
    """
    if not client: return None # Return None if client not configured
    # Format prompt using template and settings from config
    prompt = config.GEMINI_TAGGING_PROMPT_TEMPLATE.format(
        min_tags=config.GEMINI_HASHTAG_COUNT_MIN,
        max_tags=config.GEMINI_HASHTAG_COUNT_MAX,
        article_content=content
    )
    # Use model name from config
    model = client.GenerativeModel(config.GEMINI_TAGGING_MODEL_NAME)
    # Use retry count from config
    retries = config.GEMINI_API_RETRY_COUNT

    while retries > 0:
        try:
            logging.debug(f"Sending request to Gemini: {config.GEMINI_TAGGING_MODEL_NAME}")
            generation_config = genai.types.GenerationConfig(temperature=0.4) # Keep temperature setting
            # Safety settings (use explicit strings)
            safety_settings = [
                {"category": "HARM_CATEGORY_HARASSMENT", "threshold": "BLOCK_MEDIUM_AND_ABOVE"},
                {"category": "HARM_CATEGORY_HATE_SPEECH", "threshold": "BLOCK_MEDIUM_AND_ABOVE"},
                {"category": "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold": "BLOCK_MEDIUM_AND_ABOVE"},
                {"category": "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold": "BLOCK_MEDIUM_AND_ABOVE"},
            ]
            response = model.generate_content(
                prompt,
                generation_config=generation_config,
                safety_settings=safety_settings
            )

            # Handle safety blocks or empty responses
            if not response.candidates:
                 block_reason = response.prompt_feedback.block_reason if response.prompt_feedback else "Unknown"
                 logging.warning(f"Gemini response blocked/empty. Reason: {block_reason}. Prompt Feedback: {response.prompt_feedback}")
                 time.sleep(config.GEMINI_API_SLEEP_TIME) # Use sleep time from config
                 return "BLOCKED" # Return specific marker

            tags_text = response.text.strip()
            logging.debug(f"Received Gemini response: {tags_text[:100]}...")
            time.sleep(config.GEMINI_API_SLEEP_TIME) # Use sleep time from config
            return tags_text # Return the raw tag string

        except Exception as e:
            logging.error(f"Error calling Gemini API (Model: {config.GEMINI_TAGGING_MODEL_NAME}): {e}", exc_info=False)
            retries -= 1
            if retries > 0:
                # Use sleep time from config for backoff calculation
                wait_time = config.GEMINI_API_SLEEP_TIME * (config.GEMINI_API_RETRY_COUNT - retries + 1)
                logging.warning(f"Retrying Gemini API call in {wait_time}s ({retries} retries left)...")
                time.sleep(wait_time)
            else:
                logging.error("Max retries reached for Gemini API call.")
                time.sleep(config.GEMINI_API_SLEEP_TIME) # Use sleep time from config
                return None # Indicate final failure

    return None # Should only be reached if loop finishes unexpectedly

def _delete_files_with_phrases(directory, phrases):
    """Deletes files in a directory if their content contains specific phrases."""
    # (Same logic as before, uses config.CONTENT_DELETION_PHRASES)
    deleted_count = 0
    if not os.path.isdir(directory):
        logging.warning(f"Directory '{directory}' not found for deletion check.")
        return deleted_count
    logging.info(f"Starting content deletion check in '{directory}' for phrases: {phrases}")
    phrases_lower = [p.lower() for p in phrases]
    try:
        files_to_check = [f for f in os.listdir(directory) if f.endswith(".txt") and os.path.isfile(os.path.join(directory, f))]
        logging.info(f"Checking {len(files_to_check)} files for content deletion.")
        for filename in tqdm(files_to_check, desc=f"Content Deletion Check", unit="file"):
            filepath = os.path.join(directory, filename)
            try:
                with open(filepath, 'r', encoding='utf-8') as f: content = f.read()
                content_lower = content.lower()
                for phrase_lower in phrases_lower:
                    if phrase_lower in content_lower:
                        logging.warning(f"Deleting source file '{filepath}' due to content phrase: '{phrase_lower}'")
                        os.remove(filepath)
                        deleted_count += 1
                        break # Move to next file once deleted
            except Exception as e: logging.error(f"Error processing {filepath} for content deletion: {e}")
        logging.info(f"Content deletion check finished. Deleted {deleted_count} files based on content.")
    except Exception as e: logging.error(f"Error during content deletion process in {directory}: {e}")
    return deleted_count

def _is_bad_title(title):
    """Checks if a title looks like a fallback (contains '_') or is the specific marker."""
    # (Same logic as before, uses config.GEMINI_TITLE_UNAVAILABLE_MARKER)
    if title == config.GEMINI_TITLE_UNAVAILABLE_MARKER: return True
    if '_' in title: return True # Heuristic for filename-derived titles
    return False

def _filter_rows_by_title(input_filepath, output_filepath, cleaned_input_dir, original_filenames_map, delete_source):
    """
    Filters a TSV file, removing rows with bad titles based on _is_bad_title.
    Optionally deletes corresponding source files. Writes to output_filepath.
    Uses settings from config.py.
    """
    if not os.path.exists(input_filepath):
        logging.error(f"Input file for title filtering not found: {input_filepath}")
        return False, 0, 0

    temp_filepath = output_filepath + ".tmp_title"
    kept_count, removed_count, deleted_source_count = 0, 0, 0
    logging.info(f"Filtering file '{input_filepath}' based on title quality...")

    try:
        with open(input_filepath, 'r', encoding='utf-8') as infile, \
             open(temp_filepath, 'w', encoding='utf-8') as outfile:
            for i, line in enumerate(infile):
                if i == 0 and "GeneratedTags" in line: # Handle header
                    outfile.write(line); continue

                line_stripped = line.strip()
                if not line_stripped: continue

                parts = line_stripped.split('\t')
                if len(parts) != 3:
                    logging.warning(f"Malformed TSV line {i+1} during title filtering, keeping: {line_stripped[:150]}...")
                    outfile.write(line); kept_count += 1; continue

                title = parts[0].strip()
                url_or_id = parts[1].strip() # Identifier used to find original filename

                if not _is_bad_title(title):
                    outfile.write(line); kept_count += 1 # Keep good titles
                else:
                    removed_count += 1
                    logging.debug(f"Removing row {i+1} due to bad title: '{title}'")
                    # Optional Source File Deletion (uses config.GEMINI_DELETE_SOURCE_ON_BAD_TITLE)
                    if delete_source:
                        source_filename = original_filenames_map.get(url_or_id)
                        if source_filename:
                            source_filepath = os.path.join(cleaned_input_dir, source_filename)
                            if os.path.exists(source_filepath):
                                try: os.remove(source_filepath); deleted_source_count += 1; logging.warning(f"Deleted source file '{source_filepath}' due to bad title.")
                                except OSError as e: logging.error(f"Failed to delete source '{source_filepath}': {e}")
                        else: logging.warning(f"Cannot find original filename for identifier '{url_or_id}' to delete source.")

        shutil.move(temp_filepath, output_filepath)
        logging.info(f"Title filtering complete. Kept {kept_count}, removed {removed_count} rows. Output: '{output_filepath}'")
        if delete_source: logging.info(f"Deleted {deleted_source_count} source files due to bad titles.")
        return True, kept_count, removed_count

    except Exception as e: logging.error(f"Error during title filtering: {e}", exc_info=True)
    finally:
        if os.path.exists(temp_filepath): 
            try: os.remove(temp_filepath); 
            except OSError: pass
    return False, 0, 0 # Indicate failure

def _filter_error_rows(input_filepath, output_filepath, filter_error_phrases):
    """
    Filters a TSV file, removing rows where the third column indicates an error.
    Writes to output_filepath. Uses settings from config.py.
    """
    if not os.path.exists(input_filepath):
        logging.error(f"Input file for error filtering not found: {input_filepath}")
        return False, 0, 0

    temp_filepath = output_filepath + ".tmp_error"
    kept_count, removed_count = 0, 0
    filter_error_set = set(filter_error_phrases) # Use set for efficiency
    logging.info(f"Filtering '{input_filepath}' for error rows using phrases: {filter_error_phrases}")

    try:
        with open(input_filepath, 'r', encoding='utf-8') as infile, \
             open(temp_filepath, 'w', encoding='utf-8') as outfile:
            for i, line in enumerate(infile):
                if i == 0 and "GeneratedTags" in line: # Handle header
                    outfile.write(line); continue

                line_stripped = line.strip()
                if not line_stripped: continue

                parts = line_stripped.split('\t')
                if len(parts) != 3:
                    logging.warning(f"Malformed TSV line {i+1} during error filtering, keeping: {line_stripped[:150]}...")
                    outfile.write(line); kept_count += 1; continue

                tags_string = parts[2]
                contains_error = any(phrase in tags_string for phrase in filter_error_set)

                if not contains_error:
                    outfile.write(line); kept_count += 1 # Keep non-error rows
                else:
                    removed_count += 1
                    logging.debug(f"Removing error row {i+1}: Tags = '{tags_string[:50]}...'")

        shutil.move(temp_filepath, output_filepath)
        logging.info(f"Error row filtering complete. Kept {kept_count}, removed {removed_count} rows. Output: '{output_filepath}'")
        return True, kept_count, removed_count

    except Exception as e: logging.error(f"Error during error row filtering: {e}", exc_info=True)
    finally:
        if os.path.exists(temp_filepath): 
            try: os.remove(temp_filepath); 
            except OSError: pass
    return False, 0, 0 # Indicate failure

def _count_and_display_top_tags(filepath, top_n=20):
    """Counts tag frequency from the final TSV file and prints top N."""
    # (Same logic as before)
    if not os.path.exists(filepath):
        logging.error(f"Cannot count tags. Final file not found: {filepath}")
        return

    logging.info(f"Counting tag frequencies from final file: {filepath}")
    tag_counts = collections.Counter()
    line_count = 0
    try:
        with open(filepath, 'r', encoding='utf-8') as infile:
            for i, line in enumerate(infile):
                if i == 0 and "GeneratedTags" in line: continue # Skip header
                line = line.strip();
                if not line: continue
                parts = line.split('\t')
                if len(parts) == 3:
                    tags_string = parts[2]
                    if tags_string:
                         line_tags = tags_string.split(',')
                         for tag in line_tags:
                             cleaned_tag = tag.strip()
                             if cleaned_tag: tag_counts[cleaned_tag] += 1
                    line_count +=1
                elif len(parts) != 0 : logging.warning(f"Malformed line {i+1} in final file: {line[:150]}...")
        logging.info(f"Finished counting tags from {line_count} data rows.")
        if not tag_counts: logging.warning("No tags found to count."); print("\n--- No Tags Found ---"); return
        top_tags = tag_counts.most_common(top_n)
        print(f"\n--- Top {len(top_tags)} Most Frequent Tags ---")
        logging.info(f"--- Top {len(top_tags)} Most Frequent Tags ---")
        for i, (tag, count) in enumerate(top_tags): print(f"{i+1:2d}. {tag:<30} : {count}"); logging.info(f"{i+1:2d}. {tag:<30} : {count}")
        print("---------------------------------------")
    except Exception as e: logging.error(f"Error during tag counting: {e}", exc_info=True)


# --- Main Function Renamed (but performs Gemini Tagging) ---
def perform_topic_modeling():
    """
    Orchestrates the Gemini-based tagging pipeline using settings from config.py.
    (Function name kept as 'perform_topic_modeling' as requested,
     but performs tagging instead of traditional topic modeling).
    """
    logging.info("Starting Gemini Tagging Pipeline (within topic_modeler.py)...")
    # --- Setup ---
    gemini_client = configure_gemini()
    if not gemini_client:
        logging.error("Exiting: Gemini client configuration failed.")
        return # Exit if API not configured

    # Use directories/filenames from config
    input_dir = config.CLEANED_CONTENT_DIR_1
    output_dir = config.GEMINI_TAGGING_OUTPUT_DIR # Use the specific output dir
    os.makedirs(output_dir, exist_ok=True)

    raw_output_path = os.path.join(output_dir, config.GEMINI_RAW_OUTPUT_FILENAME)
    title_filtered_path = os.path.join(output_dir, config.GEMINI_TITLE_FILTERED_FILENAME)
    final_output_path = os.path.join(output_dir, config.GEMINI_FINAL_OUTPUT_FILENAME)

    # --- Step 1: Initial content deletion ---
    _delete_files_with_phrases(input_dir, config.CONTENT_DELETION_PHRASES)

    # --- Step 2 & 3: Generate Tags & Write Raw TSV ---
    logging.info(f"Starting Gemini tag generation for files in '{input_dir}'...")
    files_processed_raw = 0
    files_skipped_empty = 0
    files_failed_api = 0
    original_filenames_map = {} # Map identifier -> filename
    stop_tag_phrases_set = config.GEMINI_FILTER_TAG_PHRASES # Use config setting

    try:
        files_to_process = [f for f in os.listdir(input_dir) if f.endswith(".txt") and os.path.isfile(os.path.join(input_dir, f))]
        logging.info(f"Found {len(files_to_process)} files remaining.")
        if not files_to_process: logging.warning("No files left to process."); return

        title_marker = "标题:" # Hardcoded for now, could be moved to config

        with open(raw_output_path, 'w', encoding='utf-8') as outfile:
            outfile.write("Title\tURL\tGeneratedTags\n") # Header

            for filename in tqdm(files_to_process, desc="Generating Tags with Gemini"):
                filepath = os.path.join(input_dir, filename)
                identifier_for_doc = filename # Default

                try:
                    # File Reading
                    with open(filepath, "r", encoding="utf-8") as f: lines = f.readlines()
                    original_url, content_lines, content_started = "", [], False
                    for line in lines:
                         if line.startswith("Original URL:"): original_url = line.replace("Original URL:", "").strip(); identifier_for_doc = original_url or filename
                         elif "Cleaned Content (Mistral):" in line: content_started = True
                         elif content_started: content_lines.append(line)
                    cleaned_content_full = "".join(content_lines).strip()
                    article_title = ""; first_line = content_lines[0].strip() if content_lines else ""
                    if first_line.startswith(title_marker): article_title = first_line[len(title_marker):].strip()
                    else: article_title = os.path.splitext(filename)[0]
                    if not article_title: article_title = config.GEMINI_TITLE_UNAVAILABLE_MARKER # Use config marker

                    original_filenames_map[identifier_for_doc] = filename # Store mapping

                    if not cleaned_content_full: logging.warning(f"Skipping '{filename}': Empty."); files_skipped_empty += 1; continue

                    # Call Gemini & Filter Specific Tags
                    raw_generated_tags = generate_tags_with_gemini(gemini_client, cleaned_content_full)
                    final_tags_string = ""
                    if raw_generated_tags:
                        if raw_generated_tags in config.GEMINI_FILTER_ERROR_PHRASES: # Use config errors
                            logging.warning(f"Error marker '{raw_generated_tags}' for '{filename}'."); final_tags_string = raw_generated_tags; files_failed_api += 1
                        else:
                            tags_list = raw_generated_tags.split(','); filtered_tags = []; filtered_out_count = 0
                            for tag in tags_list:
                                cleaned_tag = tag.strip()
                                if cleaned_tag and cleaned_tag not in stop_tag_phrases_set: # Use config filter set
                                    filtered_tags.append(cleaned_tag)
                                elif cleaned_tag: logging.debug(f"Filtered tag: '{cleaned_tag}' for '{filename}'"); filtered_out_count += 1
                            if filtered_out_count > 0: logging.info(f"Filtered {filtered_out_count} stop tag(s) for '{filename}'.")
                            final_tags_string = ",".join(filtered_tags); files_processed_raw += 1
                    else:
                        logging.error(f"Failed tags for '{filename}' after retries."); final_tags_string = "ERROR_GENERATING_TAGS"; files_failed_api += 1

                    # Write Raw Row
                    safe_title = article_title.replace('\t', ' '); safe_url = identifier_for_doc.replace('\t', ' ')
                    safe_tags = final_tags_string.replace('\t', ',')
                    outfile.write(f"{safe_title}\t{safe_url}\t{safe_tags}\n")

                except Exception as e:
                    logging.error(f"Unexpected error processing file {filepath}: {e}", exc_info=True); files_failed_api += 1
                    safe_title = os.path.splitext(filename)[0].replace('\t', ' '); safe_url = identifier_for_doc.replace('\t', ' ')
                    outfile.write(f"{safe_title}\t{safe_url}\tERROR_PROCESSING_FILE\n")

        logging.info(f"Raw tag generation complete. Output: '{raw_output_path}'.")
        logging.info(f"Summary: Success={files_processed_raw}, Skipped={files_skipped_empty}, Failed/Blocked={files_failed_api}")

    except Exception as e:
        logging.error(f"Error during main processing loop: {e}", exc_info=True)
        return

    # --- Step 4: Filter Raw TSV by Title Quality ---
    title_filter_success, kept_title, removed_title = _filter_rows_by_title(
        raw_output_path, title_filtered_path, input_dir, original_filenames_map,
        config.GEMINI_DELETE_SOURCE_ON_BAD_TITLE # Use config flag
    )
    if not title_filter_success:
        logging.error("Title filtering failed. Skipping subsequent steps.")
        return

    # --- Step 5: Filter Title-Filtered TSV by Error Phrases ---
    error_filter_success, kept_final, removed_error = _filter_error_rows(
        title_filtered_path, final_output_path,
        config.GEMINI_FILTER_ERROR_PHRASES # Use config errors
    )
    if not error_filter_success:
        logging.error("Error row filtering failed. Skipping tag counting.")
        return

    # --- Step 6: Count and Display Top Tags ---
    if kept_final > 0:
        _count_and_display_top_tags(final_output_path, top_n=20)
    else:
        logging.warning("Final output file is empty after filtering. No tags to count.")

    # --- Optional Cleanup --- (Keep commented out unless needed)
    # try:
    #     if os.path.exists(raw_output_path): os.remove(raw_output_path)
    #     if os.path.exists(title_filtered_path): os.remove(title_filtered_path)
    #     logging.info("Cleaned up intermediate files.")
    # except OSError as e: logging.warning(f"Could not delete intermediate files: {e}")

    logging.info("Gemini Tagging Pipeline (within topic_modeler.py) finished.")


# --- Main Execution Block ---
if __name__ == "__main__":
    # Use print for this initial check as logging might not be fully configured if config fails
    print(f"--- Running with Python: {sys.executable} ---")

    # Attempt to configure logging using the config file path
    # BasicConfig is simple, but using FileHandler allows directing logs
    log_formatter = logging.Formatter('%(asctime)s - %(levelname)-8s - %(name)-12s - %(message)s', '%Y-%m-%d %H:%M:%S')
    root_logger = logging.getLogger()
    root_logger.setLevel(logging.INFO) # Set default level

    # Console Handler
    console_handler = logging.StreamHandler(sys.stdout)
    console_handler.setFormatter(log_formatter)
    root_logger.addHandler(console_handler)

    # Optional: File Handler (Log to a file in the script's directory or output dir)
    # log_file_path = os.path.join(config.GEMINI_TAGGING_OUTPUT_DIR, 'topic_modeler_gemini.log')
    # os.makedirs(config.GEMINI_TAGGING_OUTPUT_DIR, exist_ok=True) # Ensure dir exists
    # file_handler = logging.FileHandler(log_file_path, mode='a', encoding='utf-8')
    # file_handler.setFormatter(log_formatter)
    # root_logger.addHandler(file_handler)


    logging.info(f"Running Topic Modeler (Gemini Mode) module directly...")

    # --- Critical API Key Check ---
    if not config.GOOGLE_API_KEY or "YOUR_ACTUAL_GEMINI_API_KEY" in config.GOOGLE_API_KEY: # Removed "AIzaSy" check
        logging.error("FATAL: GOOGLE_API_KEY is not set or is using a placeholder value in config.py. Please set it correctly.")
        sys.exit(1)

    # Check input directory existence from config
    if not os.path.isdir(config.CLEANED_CONTENT_DIR_1):
         logging.error(f"Input directory '{config.CLEANED_CONTENT_DIR_1}' from config.py not found.")
         sys.exit(1)

    # Run the main pipeline function (renamed but performs tagging)
    perform_topic_modeling()

    logging.info("Topic Modeler (Gemini Mode) module execution finished.")