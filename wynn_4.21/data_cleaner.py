# data_cleaner.py

import os
import logging
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from tqdm import tqdm
from mistralai import Mistral


import config

def _call_mistral_clean_api(text, api_key, model_name, prompt_template):
    """Sends text to Mistral API for cleaning based on the provided prompt."""
    prompt = prompt_template.format(text=text)
    try:
        # Add a small delay before each API call to respect rate limits
        time.sleep(config.MISTRAL_API_TIMEOUT)

        client = Mistral(api_key=api_key)
        response = client.chat.complete(
            model=model_name,
            messages=[{"role": "user", "content": prompt}],
            temperature=0.3 # Lower temperature for more deterministic cleaning
            # Consider adding timeout parameter if available/needed
        )
        if response.choices:
            cleaned_text = response.choices[0].message.content.strip()
            # Basic check for common failure indicators returned by the model itself
            if any(phrase.lower() in cleaned_text.lower() for phrase in ["cannot access", "unable to process", "error"]):
                 logging.warning(f"Mistral indicated processing issue for input starting with: '{text[:50]}...' - Response: '{cleaned_text[:100]}...'")
            return cleaned_text
        else:
            logging.error("Mistral API returned no choices.")
            return "[ERROR: No response choices]"
        
    except Exception as e:
        logging.error(f"Unexpected error calling Mistral API: {e}")
        return f"[ERROR: Unexpected - {e}]"


def _process_file_for_cleaning(input_filepath, output_dir, api_key, model_name, prompt_template):
    """Reads a file, cleans its content using Mistral, and saves the result."""
    try:
        with open(input_filepath, 'r', encoding='utf-8') as f:
            lines = f.readlines()

        # Extract original URL and content based on the format saved by content_scraper
        original_url = ""
        content_lines = []
        content_started = False
        for line in lines:
            if line.startswith("Original URL:"):
                original_url = line.replace("Original URL:", "").strip()
            elif line.strip() == "Content:":
                content_started = True
            elif content_started:
                content_lines.append(line)

        if not content_lines:
            logging.warning(f"No content found after 'Content:' marker in {input_filepath}. Skipping cleaning.")
            return (input_filepath, 'no content found')

        raw_text = "".join(content_lines).strip()

        if not raw_text:
            logging.warning(f"Extracted content is empty for {input_filepath}. Skipping cleaning.")
            return (input_filepath, 'empty content')

        # Call Mistral API for cleaning
        cleaned_text = _call_mistral_clean_api(raw_text, api_key, model_name, prompt_template)

        # Prepare output
        output_filename = os.path.basename(input_filepath)
        output_filepath = os.path.join(output_dir, output_filename)

        # Save the cleaned content, preserving the original URL
        with open(output_filepath, 'w', encoding='utf-8') as f:
            f.write(f"Original URL: {original_url}\n") # Keep original URL for reference
            f.write("-" * 50 + "\n")
            f.write("Cleaned Content (Mistral):\n")
            f.write(cleaned_text)

        if "[ERROR:" in cleaned_text:
             # Log error but file is still created with error message
             return (input_filepath, f'cleaning failed - {cleaned_text}')
        else:
            return (input_filepath, 'success')

    except FileNotFoundError:
        logging.error(f"Input file not found during cleaning: {input_filepath}")
        return (input_filepath, 'file not found')
    except Exception as e:
        error_msg = f"Unexpected error processing file {input_filepath} for cleaning: {e}"
        logging.error(error_msg)
        return (input_filepath, error_msg)


def clean_scraped_data():
    """
    Cleans extracted website data using the Mistral API.
    Processes files in the 'extracted' directory and outputs to 'cleaned1'.
    Uses ThreadPoolExecutor for parallel processing with multiple API keys.
    """
    input_dir = config.EXTRACTED_CONTENT_DIR
    output_dir = config.CLEANED_CONTENT_DIR_1
    api_keys = config.MISTRAL_API_KEYS
    model_name = config.MISTRAL_MODEL_CLEANING
    prompt_template = config.MISTRAL_CLEANING_PROMPT

    logging.info(f"Starting data cleaning process from '{input_dir}' to '{output_dir}' using Mistral...")

    if not api_keys:
        logging.error("No Mistral API keys found in config. Cannot perform cleaning.")
        return

    if not os.path.isdir(input_dir):
        logging.error(f"Input directory not found: {input_dir}. Cannot clean data.")
        return

    os.makedirs(output_dir, exist_ok=True)
    logging.info(f"Output directory: {output_dir}")

    # List input files safely
    try:
        input_files = [os.path.join(input_dir, f) for f in os.listdir(input_dir) if f.endswith('.txt') and os.path.isfile(os.path.join(input_dir, f))]
    except FileNotFoundError:
         logging.error(f"Input directory {input_dir} not found when listing files.")
         return
    except Exception as e:
        logging.error(f"Error listing files in {input_dir}: {e}")
        return

    if not input_files:
        logging.warning(f"No .txt files found in {input_dir} to clean.")
        return

    num_workers = len(api_keys)
    logging.info(f"Using {num_workers} workers (one per API key). Processing {len(input_files)} files.")

    success_count = 0
    error_count = 0

    with ThreadPoolExecutor(max_workers=num_workers) as executor:
        # Assign API keys round-robin
        futures = {
            executor.submit(
                _process_file_for_cleaning,
                file_path,
                output_dir,
                api_keys[i % num_workers], # Cycle through API keys
                model_name,
                prompt_template
            ): file_path
            for i, file_path in enumerate(input_files)
        }

        with tqdm(total=len(futures), desc="Mistral Cleaning", unit="file") as pbar:
            for future in as_completed(futures):
                file_path = futures[future]
                try:
                    origin_file, status = future.result()
                    if status == 'success':
                        success_count += 1
                    else:
                        error_count += 1
                        # Log failures (details logged within helper functions)
                        logging.warning(f"Failed cleaning {os.path.basename(origin_file)}: {status}")
                except Exception as exc:
                    error_count += 1
                    logging.error(f"Cleaning task for {os.path.basename(file_path)} generated an exception: {exc}")

                pbar.update(1)
                pbar.set_postfix_str(f"Success: {success_count}, Errors: {error_count}")

    logging.info(f"Data cleaning API calls finished. Success: {success_count}, Failed: {error_count}")
    logging.info(f"Cleaned files saved to {output_dir}")

# --- Add this block to make the script runnable ---
if __name__ == "__main__":
    import logging
    import os
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(levelname)-8s - %(name)-12s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    import config

    logging.info("Running Data Cleaner module directly...")
    if not os.path.isdir(config.EXTRACTED_CONTENT_DIR):
         logging.error(f"Input directory {config.EXTRACTED_CONTENT_DIR} not found. Cannot run Data Cleaner.")
    elif not config.MISTRAL_API_KEYS:
         logging.error("Mistral API keys are not configured in config.py. Cannot run Data Cleaner.")
    else:
        clean_scraped_data()
    logging.info("Data Cleaner module execution finished.")
# --- End of added block ---