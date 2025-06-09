import os

def list_all_contents_recursive(root_path):
    """
    Recursively lists all subfolders and files within a given directory path.

    Args:
        root_path (str): The starting directory path to inspect.

    Returns:
        dict: A dictionary representing the directory structure.
              Keys are directory paths (relative to root_path).
              Values are lists of files directly within that directory.
              Returns empty dict and prints error messages if the path does not exist or is not a directory.
    """
    all_contents = {}

    # Validate the root path
    if not os.path.exists(root_path):
        print(f"Error: Directory not found at '{root_path}'")
        return {}
    if not os.path.isdir(root_path):
        print(f"Error: '{root_path}' is not a directory.")
        return {}

    try:
        # os.walk yields a 3-tuple for each directory it visits:
        # (dirpath, dirnames, filenames)
        # dirpath: The current directory being walked
        # dirnames: A list of names of subdirectories in dirpath (not full paths)
        # filenames: A list of names of non-directory files in dirpath (not full paths)
        for dirpath, dirnames, filenames in os.walk(root_path):
            # Calculate the path relative to the initial root_path
            relative_dirpath = os.path.relpath(dirpath, root_path)

            # Store the files for the current relative directory path
            # We sort filenames for consistent output order
            all_contents[relative_dirpath] = sorted(filenames)

    except PermissionError:
        print(f"Error: Permission denied to access '{root_path}' or its subdirectories.")
        return {}
    except Exception as e:
        print(f"An unexpected error occurred: {e}")
        return {}

    return all_contents

def print_structured_contents(contents_dict):
    """
    Prints the directory contents in a structured, hierarchical way,
    mimicking a file tree.
    """
    if not contents_dict:
        print("No contents to display or an error occurred during scanning.")
        return

    # Sort directory paths to ensure consistent and logical display order
    # (e.g., "admin" before "admin/pages")
    sorted_dirs = sorted(contents_dict.keys())

    # Iterate through each sorted directory path
    for dir_path in sorted_dirs:
        # Determine indentation level for visual hierarchy
        # Count the number of path separators to infer depth
        indent_level = dir_path.count(os.sep) if dir_path != "." else 0
        indent_space = "  " * indent_level # 2 spaces per level

        # Print the folder name
        # For the root directory '.', print "Root Directory:"
        if dir_path == ".":
            print("\nRoot Directory:")
        else:
            # Extract just the current folder name for display
            current_folder_name = os.path.basename(dir_path)
            print(f"{indent_space}Folder: {current_folder_name}/")

        # Print files within this folder
        files_in_dir = contents_dict[dir_path]
        if files_in_dir:
            for file_name in files_in_dir:
                print(f"{indent_space}  - {file_name}") # Indent files slightly more than their folder
        else:
            print(f"{indent_space}  (No files in this folder)")


# --- How to Run This Script ---
if __name__ == "__main__":
    # Set this to the absolute path of your project's root folder,
    # or use "." if you are running the script from within the project's root folder.
    
    # Example for Windows (adjust if your project is elsewhere):
    # project_root_directory = "C:\\xampp\\htdocs\\Movieticketbooking"

    # Example if you are running the script directly from the "Movieticketbooking" directory:
    project_root_directory = "."

    print(f"Scanning directory: '{project_root_directory}'")
    
    # Get the contents recursively
    all_files_and_folders = list_all_contents_recursive(project_root_directory)

    # Print the structured output
    print_structured_contents(all_files_and_folders)

