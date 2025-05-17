# link_scraper.py

import csv
import logging
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, WebDriverException

import config

# Try to import google_colab_selenium; if not present, use standard Selenium.
try:
    import google_colab_selenium as gs
    logging.info("Detected Colab environment, using google_colab_selenium.")
except (ImportError, AssertionError): # Catch both ImportError and the specific AssertionError
    logging.info("Not running in Colab or google_colab_selenium not found, using standard Selenium.")
    gs = None


def get_links_selenium(url, timeout, output_filename, mode='w'):
    """
    Retrieves all links from a webpage using Selenium and saves them to a CSV file.
    Uses google_colab_selenium if available, otherwise falls back to standard Selenium.
    """
    driver = None # Initialize driver to None
    try:
        if gs is not None:
            driver = gs.Chrome()
            logging.info("Using google_colab_selenium Chrome driver.")
        else:
            options = webdriver.ChromeOptions()
            # Headless is generally preferred for scraping unless debugging UI
            options.add_argument('--headless')
            options.add_argument('--no-sandbox') # Often needed in containerized environments
            options.add_argument('--disable-dev-shm-usage') # Overcomes limited resource problems
            options.add_argument('--disable-gpu') # Applicable to headless
            options.add_argument('--no-proxy-server') # Add this line to disable proxy
            # ↓↓↓ 添加这行打印语句 ↓↓↓
            print("DEBUG: Chrome Arguments:", options.arguments)
            # ↑↑↑ 添加这行打印语句 ↑↑↑
            driver = webdriver.Chrome(options=options)
            logging.info("Using standard Selenium headless Chrome driver.")

        logging.info(f"Attempting to access URL: {url}")
        driver.get(url)
        try:
            WebDriverWait(driver, timeout).until(
                EC.presence_of_all_elements_located((By.TAG_NAME, "a"))
            )
            logging.info("Page loaded and 'a' tags found.")
        except TimeoutException:
            logging.warning(f"Timed out waiting for 'a' elements after {timeout} seconds on {url}.")
            # Continue execution, might get some links anyway or none
            pass # Or return None / raise error if elements are critical

        links = []
        link_elements = driver.find_elements(By.TAG_NAME, "a")
        logging.info(f"Found {len(link_elements)} 'a' elements.")
        for link_element in link_elements:
            href = link_element.get_attribute("href")
            if href: # Ensure href is not None or empty
                links.append(href)

        if not links:
             logging.warning(f"No links extracted from {url}.")
             return False # Indicate failure or no links found

        with open(output_filename, mode, newline='', encoding='utf-8') as csvfile:
            writer = csv.writer(csvfile)
            if mode == 'w':  # Write header only when overwriting
                writer.writerow(['Links']) # Write header
            for link in links:
                writer.writerow([link])
        logging.info(f"Successfully saved {len(links)} links to {output_filename}")
        return True

    except WebDriverException as e:
        logging.error(f"WebDriver error occurred during link scraping: {e}")
        return False
    except Exception as e:
        logging.error(f"An unexpected error occurred during link scraping: {e}")
        return False
    finally:
        if driver:
            driver.quit()
            logging.info("WebDriver closed.")


def scrape_main_links():
    """
    Scrape the main website configured in config.py for links.
    """
    logging.info("Starting main link scraping process...")
    base_url = config.SCRAPER_INPUT_URL.rsplit("p=", 1)[0]  # Assumes SCRAPER_INPUT_URL ends with 'p=1'
    first = True
    for page in config.SCRAPER_PAGES:
        url = base_url + f"p={page}"
        mode = 'w' if first else 'a'
        success = get_links_selenium(
            url=url,
            timeout=config.SELENIUM_TIMEOUT,
            output_filename=config.RAW_LINKS_CSV,
            mode=mode
        )
        if success:
            logging.info(f"Link scraping completed for {url}")
        else:
            logging.error(f"Link scraping failed for {url}")
        first = False

# --- Add this block to make the script runnable ---
if __name__ == "__main__":
    import logging
    logging.basicConfig(
        level=logging.INFO,
        format='%(asctime)s - %(levelname)-8s - %(name)-12s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    # import config # Assuming functions already import config

    logging.info("Running Link Scraper module directly...")
    scrape_main_links()
    logging.info("Link Scraper module execution finished.")
# --- End of added block ---