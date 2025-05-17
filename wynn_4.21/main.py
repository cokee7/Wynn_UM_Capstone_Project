# main.py

import logging
import time
import argparse
import os # Import os for path checking

# Import functions from custom modules
import config # Central configuration
import cleanup_utils
import link_scraper
import link_filter
import content_scraper
import data_cleaner
import topic_modeler # This now runs the Gemini tagging pipeline
import report_generator # This now generates topic-specific reports
import db_writer # This now has functions for tags and topic reports

# --- Basic Logging Setup ---
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)-8s - %(name)-12s - %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
)
# Reduce verbosity of noisy libraries
logging.getLogger("selenium.webdriver.remote.remote_connection").setLevel(logging.WARNING)
logging.getLogger("urllib3.connectionpool").setLevel(logging.WARNING)
# Keep jieba logging level if needed, otherwise set higher
# logging.getLogger("jieba").setLevel(logging.INFO)
logging.getLogger("google.generativeai").setLevel(logging.WARNING) # Reduce Gemini SDK noise if needed


# --- Main Pipeline Execution ---

def run_pipeline():
    """Executes the complete data processing pipeline step-by-step."""
    start_time = time.time()
    print("=============================================")
    print("======= Starting Data Pipeline Run ========")
    logging.info("======= Starting Data Pipeline Run ========")
    print("=============================================")

    # --- Step 0: Cleanup ---
    print("\n----- Step 0: Cleaning up previous outputs -----")
    logging.info("----- Step 0: Cleaning up previous outputs -----")
    try:
        cleanup_utils.run_cleanup()
        print("----- Step 0: Cleanup Complete -----")
        logging.info("----- Step 0: Cleanup Complete -----\n")
    except Exception as e:
        print("ERROR during Cleanup. Check logs.")
        logging.exception("ERROR: Unhandled exception during Step 0 (Cleanup).")
        # Decide if pipeline should continue if cleanup fails
        # return # Optional: Stop pipeline if cleanup fails critically


    # --- Step 1: Scrape Main Links ---
    print("----- Step 1: Scraping main website links -----")
    logging.info("----- Step 1: Scraping main website links -----")
    try:
        link_scraper.scrape_main_links()
        print("----- Step 1: Link Scraping Complete -----")
        logging.info("----- Step 1: Link Scraping Complete -----\n")
    except Exception as e:
        print("FATAL ERROR during Link Scraping. Check logs.")
        logging.exception("FATAL: Unhandled exception during Step 1 (Link Scraping). Stopping pipeline.")
        return


    # --- Step 2: Filter Links ---
    print("----- Step 2: Filtering scraped links -----")
    logging.info("----- Step 2: Filtering scraped links -----")
    try:
        link_filter.filter_links()
        print("----- Step 2: Link Filtering Complete -----")
        logging.info("----- Step 2: Link Filtering Complete -----\n")
    except Exception as e:
        print("ERROR during Link Filtering. Check logs.")
        logging.exception("ERROR: Unhandled exception during Step 2 (Link Filtering).")
        # Continue pipeline?


    # --- Step 3: Scrape Content ---
    print("----- Step 3: Scraping website content from filtered links -----")
    logging.info("----- Step 3: Scraping website content from filtered links -----")
    try:
        content_scraper.scrape_all_websites_content()
        print("----- Step 3: Content Scraping Complete -----")
        logging.info("----- Step 3: Content Scraping Complete -----\n")
    except Exception as e:
         print("ERROR during Content Scraping. Check logs.")
         logging.exception("ERROR: Unhandled exception during Step 3 (Content Scraping).")
         # Continue pipeline?


    # --- Step 4: Clean Data (Mistral or preferred cleaner) ---
    print(f"----- Step 4: Cleaning scraped data ({getattr(config, 'MISTRAL_MODEL_CLEANING', 'Default Cleaner')}) -----")
    logging.info(f"----- Step 4: Cleaning scraped data ({getattr(config, 'MISTRAL_MODEL_CLEANING', 'Default Cleaner')}) -----")
    try:
        data_cleaner.clean_scraped_data()
        print("----- Step 4: Data Cleaning Complete -----")
        logging.info("----- Step 4: Data Cleaning Complete -----\n")
    except Exception as e:
         print("ERROR during Data Cleaning. Check logs.")
         logging.exception("ERROR: Unhandled exception during Step 4 (Data Cleaning).")
         # Continue pipeline?


    # --- Step 5: Topic Modeling (Now Gemini Tagging) ---
    print(f"----- Step 5: Performing Gemini topic tagging ({config.GEMINI_TAGGING_MODEL_NAME}) -----")
    logging.info(f"----- Step 5: Performing Gemini topic tagging ({config.GEMINI_TAGGING_MODEL_NAME}) -----")
    try:
        # Assumes topic_modeler.py now runs the Gemini tagging pipeline
        topic_modeler.perform_topic_modeling() # This function name is kept, but logic changed
        print("----- Step 5: Gemini Tagging Complete -----")
        logging.info("----- Step 5: Gemini Tagging Complete -----\n")
    except Exception as e:
         print("ERROR during Gemini Tagging. Check logs.")
         logging.exception("ERROR: Unhandled exception during Step 5 (Gemini Tagging).")
         # Continue pipeline?


    # --- Step 6: Generate Topic Reports (Gemini) ---
    print(f"----- Step 6: Generating topic-specific reports with Gemini API ({config.GEMINI_API_MODEL}) -----")
    logging.info(f"----- Step 6: Generating topic-specific reports with Gemini API ({config.GEMINI_API_MODEL}) -----")
    # We don't get a simple boolean back, rely on logs/exceptions
    step6_exception = False
    try:
        # Call the function that generates multiple reports based on top tags
        report_generator.generate_topic_reports() # Generates files in Topic_Reports subdir
        print("----- Step 6: Topic Report Generation Finished -----")
        logging.info("----- Step 6: Topic Report Generation Finished (Check logs for details) -----\n")
    except Exception as e:
         step6_exception = True
         print("ERROR during Topic Report Generation. Check logs.")
         logging.exception("ERROR: Unhandled exception during Step 6 (Topic Report Generation).")
         print("----- Step 6: Topic Report Generation Failed -----")
         logging.info("----- Step 6: Topic Report Generation Failed -----\n")


    # --- Step 7: Write Gemini Tags to Database ---
    print("----- Step 7: Writing Gemini tags to database (topics_file) -----")
    logging.info("----- Step 7: Writing Gemini tags to database (topics_file) -----")
    try:
        # Call the function that writes tags to the topics_file table
        db_writer.write_gemini_tags_to_database()
        print("----- Step 7: Gemini Tags Database Write Complete -----")
        logging.info("----- Step 7: Gemini Tags Database Write Finished (Check logs) -----\n")
    except Exception as e:
         print("ERROR during Gemini Tags Database Write. Check logs.")
         logging.exception("ERROR: Unhandled exception during Step 7 (Gemini Tags Database Write).")
         # Continue pipeline?


    # --- Step 8: Write Topic Reports to Database ---
    print("----- Step 8: Writing topic reports to database (report_file) -----")
    logging.info("----- Step 8: Writing topic reports to database (report_file) -----")
    # Check if the directory where reports *should* be exists.
    # The DB writer function will handle the case where the dir exists but is empty.
    topic_report_dir = os.path.join(config.GEMINI_TAGGING_OUTPUT_DIR, "Topic_Reports")

    if step6_exception:
        logging.warning("Skipping Step 8 (Write Topic Reports to DB) because Step 6 (Report Generation) encountered an exception.")
        print("----- Step 8: Skipped (Report generation failed) -----")
    elif os.path.isdir(topic_report_dir):
        try:
            # Call the function that reads report files and writes to report_file table
            db_writer.write_topic_reports_to_database()
            print("----- Step 8: Topic Reports Database Write Complete -----")
            logging.info("----- Step 8: Topic Reports Database Write Finished (Check logs) -----\n")
        except Exception as e:
             print("ERROR during Topic Reports Database Write. Check logs.")
             logging.exception("ERROR: Unhandled exception during Step 8 (Topic Reports Database Write).")
             print("----- Step 8: Topic Reports Database Write Failed -----")
             logging.info("----- Step 8: Topic Reports Database Write Failed -----\n")
    else:
        logging.warning(f"Skipping Step 8 (Write Topic Reports to DB) because the expected report directory '{topic_report_dir}' was not found. Report generation might have failed or produced no reports.")
        print(f"----- Step 8: Skipped (Topic report directory '{topic_report_dir}' not found) -----")


    # --- Pipeline End ---
    end_time = time.time()
    total_duration = end_time - start_time
    print("\n=============================================")
    print("======= Data Pipeline Run Finished ========")
    print(f"Total execution time: {total_duration:.2f} seconds")
    logging.info("======= Data Pipeline Run Finished ========")
    logging.info(f"Total execution time: {total_duration:.2f} seconds")
    print("=============================================")


if __name__ == "__main__":
    # Optional: Add argparse here if you want command-line flags later
    # parser = argparse.ArgumentParser(description="Run the data processing pipeline.")
    # Add arguments if needed...
    # args = parser.parse_args()
    run_pipeline()