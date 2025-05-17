# report_generator.py

import os
import logging
import time
import google.generativeai as genai
from tqdm import tqdm
import collections
import sys
import re # Keep re for sanitization
import shutil # Keep shutil if you need intermediate file cleanup later

# Import central configuration
import config

# --- Logging Setup ---
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)-8s - %(name)-12s - %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)

# --- Helper Functions ---

def configure_gemini():
    """Configures the Gemini API client using settings from config.py."""
    if not config.GOOGLE_API_KEY:
        logging.error("GOOGLE_API_KEY environment variable is not set in config or environment. Cannot configure Gemini.")
        return None
    try:
        genai.configure(api_key=config.GOOGLE_API_KEY)
        logging.info("Gemini API configured successfully for report generation.")
        return genai
    except Exception as e:
        logging.error(f"Error configuring Gemini API for report generation: {e}", exc_info=True)
        return None

# --- REVISED: Consistent Filename Sanitization (Removes Protocol, Keeps Dots) ---
def sanitize_url_to_filename_stem(url):
    if not url:
        return None
    stem = re.sub(r'^https?://', '', url)
    # 2. replace ANY character that is NOT letter, digit, or hyphen → underscore
    stem = re.sub(r'[^0-9A-Za-z\-]+', '_', stem)
    # 3. trim leading/trailing underscores
    return stem.strip('_')


# --- MODIFIED: _find_cleaned_file_path ---
def _find_cleaned_file_path(identifier, cleaned_dir):
    """
    Tries to find the corresponding cleaned file based on the identifier.
    Uses sanitize_url_to_filename_stem to generate the expected filename
    (without protocol, keeping dots) from the URL identifier.
    """
    # Strategy 1: Identifier might already BE the filename (fallback)
    if identifier.endswith(".txt"):
        potential_path_direct = os.path.join(cleaned_dir, identifier)
        if os.path.exists(potential_path_direct):
            logging.debug(f"Found file directly using identifier as filename: {identifier}")
            return potential_path_direct

    # Strategy 2 (Primary): Sanitize the identifier (assuming it's a URL)
    # to get the stem *without* the protocol.
    filename_stem = sanitize_url_to_filename_stem(identifier)
    if filename_stem:
        potential_filename = filename_stem + ".txt"
        potential_path = os.path.join(cleaned_dir, potential_filename)
        if os.path.exists(potential_path):
            logging.debug(f"Found file using sanitized name '{potential_filename}' from identifier '{identifier}'")
            return potential_path
        else:
            # Log the specific sanitized name that wasn't found
            logging.warning(f"Sanitized filename '{potential_filename}' (derived from '{identifier}') not found in '{cleaned_dir}'.")
    else:
         # Log if sanitization failed (e.g., empty identifier)
         logging.warning(f"Could not generate valid filename stem from identifier: {identifier}")


    # If neither worked, log the overall failure
    logging.warning(f"Could not find cleaned file in '{cleaned_dir}' for identifier: {identifier} (tried direct match and sanitization).")
    return None # Indicate file not found


def _read_tags_and_map_content(tags_filepath):
    """
    Reads the final tags TSV file, counts tag frequencies,
    and maps tags to their associated identifiers (URLs/filenames).
    (Includes robust parsing for potential spacing issues)
    """
    if not os.path.exists(tags_filepath):
        logging.error(f"Tags file not found: {tags_filepath}")
        return None, None

    logging.info(f"Reading tags and mapping content from: {tags_filepath}")
    tag_counts = collections.Counter()
    tag_to_identifiers = collections.defaultdict(list)
    rows_processed = 0
    skipped_malformed = 0

    try:
        with open(tags_filepath, 'r', encoding='utf-8') as infile:
            for i, line in enumerate(infile):
                if i == 0 and "GeneratedTags" in line: continue # Skip header
                line = line.strip()
                if not line: continue

                parts = line.split('\t')
                identifier = None
                tags_string = None

                if len(parts) == 3:
                    identifier = parts[1].strip()
                    tags_string = parts[2].strip()
                elif len(parts) > 1: # Try to handle spacing issues
                     url_match = re.search(r'(https?://[^\s]+)', line)
                     if url_match:
                         identifier = url_match.group(1)
                         potential_parts = line.split(identifier)
                         if len(potential_parts) == 2:
                              tags_string = potential_parts[1].strip()
                              logging.debug(f"Line {i+1}: Parsed using URL pattern finding.")
                         else:
                              logging.warning(f"Skipping malformed line {i+1} (split issue): {line[:150]}..."); skipped_malformed += 1; continue
                     else:
                         logging.warning(f"Skipping malformed line {i+1} (no tabs/URL): {line[:150]}..."); skipped_malformed += 1; continue
                else:
                    logging.warning(f"Skipping malformed line {i+1} (no tabs/URL/tags): {line[:150]}..."); skipped_malformed += 1; continue

                # Process valid identifier and tags
                if identifier and tags_string:
                    tags = [tag.strip() for tag in tags_string.split(',') if tag.strip()]
                    for tag in tags:
                        tag_counts[tag] += 1
                        # Using defaultdict, no need to check if identifier exists
                        tag_to_identifiers[tag].append(identifier)
                    rows_processed += 1
                elif identifier: # Row had identifier but no valid tags found after splitting
                     rows_processed += 1
                     logging.debug(f"Row {i+1} processed, but no valid tags found after splitting.")

        logging.info(f"Processed {rows_processed} rows from tags file (skipped {skipped_malformed} malformed lines).")
        logging.info(f"Found {len(tag_counts)} unique tags.")
        logging.info(f"Mapped {len(tag_to_identifiers)} tags to identifiers.")
        return tag_counts, tag_to_identifiers

    except Exception as e:
        logging.error(f"Error reading or processing tags file '{tags_filepath}': {e}", exc_info=True)
        return None, None


def _extract_cleaned_content_from_file(filepath):
    """Reads a cleaned file and extracts only the content after the header."""
    try:
        with open(filepath, "r", encoding="utf-8") as f: lines = f.readlines()
        content_lines = []; content_started = False
        for line in lines:
             # Robust check for different possible headers
             if "Cleaned Content (Mistral):" in line or "Cleaned Content (Gemini):" in line:
                 content_started = True
             elif content_started:
                 content_lines.append(line)
        cleaned_content = "".join(content_lines).strip()
        return cleaned_content
    except FileNotFoundError:
        logging.error(f"File not found during content extraction: {filepath}")
        return ""
    except Exception as e:
        logging.error(f"Error extracting content from {filepath}: {e}")
        return ""


def _get_content_for_topic(identifiers, cleaned_dir):
    """Retrieves and concatenates cleaned content for a list of identifiers."""
    all_content = []
    found_count = 0
    logging.info(f"Retrieving content for {len(identifiers)} identifiers...")
    for identifier in identifiers:
        # Use the updated lookup function
        filepath = _find_cleaned_file_path(identifier, cleaned_dir)
        if filepath:
            content = _extract_cleaned_content_from_file(filepath)
            if content:
                all_content.append(content)
                found_count += 1
        # Warning for not found files logged within _find_cleaned_file_path
    logging.info(f"Successfully retrieved content from {found_count}/{len(identifiers)} associated files.")
    # Join content with clear separators
    return "\n\n---===---\n\n".join(all_content) # Use a distinct separator


def _generate_single_report(client, topic_name, combined_content):
    """Generates a single report for a given topic using Gemini."""
    if not client or not combined_content:
        logging.error(f"Skipping report generation for topic '{topic_name}': Missing Gemini client or combined content.")
        return None

    logging.info(f"Generating report for topic: '{topic_name}'...")
    # Use the report prompt template from config.py
    prompt = config.REPORT_PROMPT_TEMPLATE.format(
        combined_news_content=combined_content
    )

    # Use the report generation model specified in config.py
    model = client.GenerativeModel(config.GEMINI_API_MODEL)
    # Use API settings from config.py
    retries = config.GEMINI_API_RETRY_COUNT
    sleep_time = config.GEMINI_API_SLEEP_TIME

    while retries > 0:
        try:
            logging.debug(f"Sending report generation request to Gemini: {config.GEMINI_API_MODEL}")
            # Configure generation parameters (temperature, safety, etc.)
            generation_config = genai.types.GenerationConfig(
                temperature=0.5, # Adjust as needed for report style
                # max_output_tokens=8192 # Consider setting if reports are truncated
            )
            safety_settings = [ # Standard safety settings
                {"category": c, "threshold": "BLOCK_MEDIUM_AND_ABOVE"}
                for c in genai.types.HarmCategory # Use the enum values directly here if supported by SDK version or list strings explicitly
            ]
            # Explicit string list for safety (more compatible)
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

            # Check for blocked response
            if not response.candidates:
                 block_reason = response.prompt_feedback.block_reason if response.prompt_feedback else "Unknown"
                 logging.warning(f"Report generation blocked for topic '{topic_name}'. Reason: {block_reason}.")
                 time.sleep(sleep_time) # Wait before returning failure
                 return None # Indicate block/failure

            report_text = response.text.strip()
            logging.info(f"Successfully generated report for topic: '{topic_name}'.")
            time.sleep(sleep_time) # Wait after successful call
            return report_text

        except Exception as e:
            logging.error(f"Error calling Gemini API for report (Topic: '{topic_name}', Model: {config.GEMINI_API_MODEL}): {e}", exc_info=False)
            retries -= 1
            if retries > 0:
                wait_time = sleep_time * (config.GEMINI_API_RETRY_COUNT - retries + 1)
                logging.warning(f"Retrying report generation for '{topic_name}' in {wait_time}s ({retries} retries left)...")
                time.sleep(wait_time)
            else:
                logging.error(f"Max retries reached for report generation (Topic: '{topic_name}').")
                time.sleep(sleep_time) # Wait after max retries
                return None
    return None


# --- Main Orchestration Function ---
def generate_topic_reports(top_n=10):
    """
    Generates individual market reports for the top N most frequent topics.
    """
    logging.info("Starting Top Topic Report Generation Pipeline...")

    # --- Setup ---
    gemini_client = configure_gemini()
    if not gemini_client:
        logging.error("Exiting: Gemini client configuration failed.")
        return

    # Use paths from config
    tags_dir = config.GEMINI_TAGGING_OUTPUT_DIR
    tags_filename = config.GEMINI_FINAL_OUTPUT_FILENAME
    tags_filepath = os.path.join(tags_dir, tags_filename)
    cleaned_content_dir = config.CLEANED_CONTENT_DIR_1
    # Create a specific subdirectory for these reports
    report_output_dir = os.path.join(tags_dir, "Topic_Reports")
    os.makedirs(report_output_dir, exist_ok=True)

    # --- Step 1 & 2: Read Tags, Count Frequencies, Map to Identifiers ---
    tag_counts, tag_to_identifiers = _read_tags_and_map_content(tags_filepath)
    if tag_counts is None or tag_to_identifiers is None:
        logging.error("Failed to read tags or map identifiers. Cannot generate reports.")
        return
    if not tag_counts:
        logging.warning("No tags found after reading the final tags file. No reports to generate.")
        return

    # --- Step 3: Identify Top N Tags ---
    top_tags = tag_counts.most_common(top_n)
    logging.info(f"Identified Top {len(top_tags)} Topics (Tags) for report generation:")
    for i, (tag, count) in enumerate(top_tags):
        logging.info(f"  {i+1}. {tag} (Count: {count})")

    # --- Step 4: Iterate, Get Content, Generate & Save Reports ---
    reports_generated = 0
    reports_failed = 0
    for i, (topic_tag, count) in enumerate(tqdm(top_tags, desc="Generating Topic Reports")):
        logging.info(f"\n--- Processing Topic {i+1}/{len(top_tags)}: '{topic_tag}' ---")

        # 4a: Get associated identifiers
        identifiers = tag_to_identifiers.get(topic_tag)
        if not identifiers:
            logging.warning(f"No identifiers found mapped to topic '{topic_tag}'. Skipping report.")
            reports_failed += 1
            continue

        # 4b: Retrieve and combine content
        combined_content = _get_content_for_topic(identifiers, cleaned_content_dir)
        if not combined_content:
            logging.warning(f"Could not retrieve any valid content for topic '{topic_tag}'. Skipping report.")
            reports_failed += 1
            continue
        logging.info(f"Combined content length for '{topic_tag}': {len(combined_content)} characters.")

        # 4c: Generate report using Gemini
        report_text = _generate_single_report(gemini_client, topic_tag, combined_content)

        # 4d: Save report
        if report_text:
            # Sanitize topic tag for use in filename
            safe_topic_name = re.sub(r'[\\/:*?"<>|]+', '_', topic_tag) # Replace invalid chars
            # Optional: limit filename length if tags are very long
            safe_topic_name = safe_topic_name[:100] # Limit length
            report_filename = f"report_topic_{safe_topic_name}.txt"
            report_filepath = os.path.join(report_output_dir, report_filename)
            try:
                with open(report_filepath, 'w', encoding='utf-8') as f:
                    f.write(report_text)
                logging.info(f"Successfully saved report for topic '{topic_tag}' to '{report_filepath}'")
                reports_generated += 1
            except Exception as e:
                logging.error(f"Failed to save report file '{report_filepath}': {e}")
                reports_failed += 1
        else:
            logging.error(f"Report generation failed for topic '{topic_tag}'.")
            reports_failed += 1

    # --- Final Summary ---
    logging.info("--- Report Generation Summary ---")
    logging.info(f"Reports Generated Successfully: {reports_generated}")
    logging.info(f"Reports Failed/Skipped: {reports_failed}")
    logging.info(f"Topic reports saved in: '{report_output_dir}'")
    logging.info("Top Topic Report Generation Pipeline finished.")


# --- Main Execution Block ---
if __name__ == "__main__":
    # Use print for this initial check as logging might not be fully configured if config fails
    print(f"--- Running with Python: {sys.executable} ---")

    # Setup Logging
    log_formatter = logging.Formatter('%(asctime)s - %(levelname)-8s - %(name)-12s - %(message)s', '%Y-%m-%d %H:%M:%S')
    root_logger = logging.getLogger()
    # Ensure handlers aren't added multiple times if script is re-run in same process
    if not root_logger.hasHandlers():
        root_logger.setLevel(logging.INFO)
        console_handler = logging.StreamHandler(sys.stdout)
        console_handler.setFormatter(log_formatter)
        root_logger.addHandler(console_handler)
        # Optional: Add FileHandler here if desired

    logging.info("Running Topic Report Generator module directly...")


    # Check if necessary input file/directory exists
    tags_filepath = os.path.join(config.GEMINI_TAGGING_OUTPUT_DIR, config.GEMINI_FINAL_OUTPUT_FILENAME)
    if not os.path.exists(tags_filepath):
        logging.error(f"Required input tags file not found: '{tags_filepath}'. Please run the topic_modeler.py (Gemini mode) first.")
        sys.exit(1)
    if not os.path.isdir(config.CLEANED_CONTENT_DIR_1):
        logging.error(f"Required cleaned content directory not found: '{config.CLEANED_CONTENT_DIR_1}'.")
        sys.exit(1)

    # Run the main report generation function
    generate_topic_reports(top_n=10) # Generate reports for top 10 topics

    logging.info("Topic Report Generator module execution finished.")