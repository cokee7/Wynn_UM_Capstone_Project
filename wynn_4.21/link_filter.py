# link_filter.py

import csv
import logging
from urllib.parse import urlparse
import config
import os

def _is_subdomain(link_domain, excluded_domain):
    """Check if the link domain matches or is a subdomain of the excluded domain."""
    # Helper function, underscore indicates internal use within the module
    link_parts = link_domain.lower().split('.')
    excluded_parts = excluded_domain.lower().split('.')
    # A domain cannot be a subdomain of a shorter domain name
    if len(link_parts) < len(excluded_parts):
        return False
    # Check if the end parts match
    return link_parts[-len(excluded_parts):] == excluded_parts

def filter_links():
    """
    Reads links from raw CSV, filters out unwanted domains and duplicates,
    and writes the cleaned links to the filtered CSV file specified in config.
    """
    logging.info(f"Starting link filtering from {config.RAW_LINKS_CSV}...")
    input_filename = config.RAW_LINKS_CSV
    output_filename = config.FILTERED_LINKS_CSV
    excluded_domains = config.EXCLUDED_DOMAINS

    if not os.path.exists(input_filename):
        logging.error(f"Input file not found: {input_filename}. Cannot filter links.")
        return

    seen_urls = set()
    output_rows = []
    valid_links_count = 0
    filtered_count = 0
    duplicate_count = 0

    try:
        with open(input_filename, 'r', encoding='utf-8', newline='') as infile:
            reader = csv.reader(infile)
            try:
                header = next(reader) # Read header
                output_rows.append(header)
            except StopIteration:
                logging.warning(f"Input file {input_filename} is empty or has no header.")
                return # Nothing to process

            for row_idx, row in enumerate(reader):
                if not row:
                    logging.warning(f"Skipping empty row at index {row_idx+1} in {input_filename}")
                    continue
                if len(row) > 1:
                     logging.warning(f"Row {row_idx+1} has more than one column, using only the first: {row}")
                url = row[0].strip()

                if not url:
                    logging.warning(f"Skipping empty URL at row index {row_idx+1}")
                    continue

                # 1. Check for duplicates
                if url in seen_urls:
                    duplicate_count += 1
                    continue

                # 2. Parse and check if URL should be excluded
                try:
                    # 解析URL
                    parsed = urlparse(url if '//' in url else '//' + url)
                    domain = parsed.netloc
                    path = parsed.path
                    
                    if not domain:
                        logging.warning(f"Could not parse domain from URL: {url}. Skipping.")
                        filtered_count += 1
                        continue
                    
                    # 检查是否应该排除
                    should_exclude = False
                    
                    for excluded in excluded_domains:
                        # 处理带路径的排除规则（如 www.yicai.com/video）
                        if '/' in excluded:
                            excluded_domain = excluded.split('/')[0]
                            excluded_path = '/' + '/'.join(excluded.split('/')[1:])
                            
                            # 检查域名是否匹配且路径以排除路径开头
                            if domain == excluded_domain and path.startswith(excluded_path):
                                logging.info(f"排除URL: {url} - 匹配排除规则: {excluded}")
                                should_exclude = True
                                break
                        # 处理纯域名的排除规则（如 xueqiu.com）
                        else:
                            if _is_subdomain(domain, excluded):
                                logging.info(f"排除URL: {url} - 域名匹配排除规则: {excluded}")
                                should_exclude = True
                                break
                    
                    if should_exclude:
                        filtered_count += 1
                        continue

                except ValueError as e:
                    logging.warning(f"Error parsing URL '{url}': {e}. Skipping.")
                    filtered_count += 1
                    continue


                # If passes all checks, add it
                seen_urls.add(url)
                output_rows.append([url]) # Store as a list for writerows
                valid_links_count += 1

    except FileNotFoundError:
         logging.error(f"Input file {input_filename} disappeared during processing.")
         return
    except Exception as e:
        logging.error(f"An error occurred while reading {input_filename}: {e}")
        return


    # Write the filtered links
    try:
        with open(output_filename, 'w', newline='', encoding='utf-8') as outfile:
            writer = csv.writer(outfile)
            writer.writerows(output_rows)
        logging.info(f"Filtering complete. Saved {valid_links_count} valid links to {output_filename}.")
        logging.info(f"Filtered out: {filtered_count} (excluded domain/invalid) and {duplicate_count} (duplicates).")
    except IOError as e:
        logging.error(f"Could not write filtered links to {output_filename}: {e}")
    except Exception as e:
        logging.error(f"An unexpected error occurred while writing {output_filename}: {e}")

# --- Add this block to make the script runnable ---
if __name__ == "__main__":
    import logging
    import os # Needed for exists check potentially
    logging.basicConfig(
        level=logging.DEBUG,
        format='%(asctime)s - %(levelname)-8s - %(name)-12s - %(message)s',
        datefmt='%Y-%m-%d %H:%M:%S'
    )
    import config # Config needed to know input/output files

    logging.info("Running Link Filter module directly...")
    # Optional: Check if input file exists before running
    if not os.path.exists(config.RAW_LINKS_CSV):
         logging.error(f"Input file {config.RAW_LINKS_CSV} not found. Cannot run Link Filter.")
    else:
        filter_links()
    logging.info("Link Filter module execution finished.")
# --- End of added block ---