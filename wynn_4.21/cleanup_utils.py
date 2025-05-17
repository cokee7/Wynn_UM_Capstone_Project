# cleanup_utils.py

import os
import shutil
import logging
import config

def run_cleanup():
    """Removes specified output files and directories."""
    logging.info("Starting cleanup...")
    # Remove specified CSV files
    for file in config.OUTPUT_FILES_TO_CLEAN:
        if os.path.exists(file):
            try:
                os.remove(file)
                logging.info(f"Removed file: {file}")
            except OSError as e:
                logging.error(f"Error removing file {file}: {e}")
        else:
            logging.info(f"File not found, skipping removal: {file}")

    # Remove specified output directories
    for folder in config.OUTPUT_DIRS_TO_CLEAN:
        if os.path.exists(folder):
            try:
                shutil.rmtree(folder)
                logging.info(f"Removed directory: {folder}")
            except OSError as e:
                logging.error(f"Error removing directory {folder}: {e}")
        else:
            logging.info(f"Directory not found, skipping removal: {folder}")

    logging.info("Cleanup complete.")

# --- Add this block to make the script runnable ---
if __name__ == "__main__":
    import logging
    # Basic logging setup for standalone run
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(levelname)-8s - %(name)-12s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    # Need to import config if not already done globally in the module
    # (assuming functions within the module already import config as needed)
    # import config

    logging.info("Running Cleanup Utility directly...")
    run_cleanup()
    logging.info("Cleanup Utility execution finished.")
# --- End of added block ---