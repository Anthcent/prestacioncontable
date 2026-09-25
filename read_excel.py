import sys
import subprocess

def install_and_read():
    try:
        import pandas as pd
        import xlrd
    except ImportError:
        subprocess.check_call([sys.executable, "-m", "pip", "install", "pandas", "xlrd"])
        import pandas as pd
        import xlrd

    file_path = "PLANILLAS FIJOS VIEJO FORMATO  2023- copia (1).xls"
    
    try:
        xls = pd.ExcelFile(file_path, engine='xlrd')
        with open("excel_report_py.txt", "w", encoding="utf-8") as f:
            f.write(f"Sheet Names: {xls.sheet_names}\n\n")
            
            for sheet_name in xls.sheet_names:
                f.write(f"--- Sheet: {sheet_name} ---\n")
                df = pd.read_excel(xls, sheet_name=sheet_name)
                # write first 30 rows and 20 columns
                f.write(df.iloc[:30, :20].to_string())
                f.write("\n\n")
        print("Success")
    except Exception as e:
        print(f"Error: {e}")

if __name__ == "__main__":
    install_and_read()
