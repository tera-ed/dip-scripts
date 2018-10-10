


mysql -u dip_crm_user dip_crm_db -e"insert into t_excel_media_history_mini select * from t_excel_media_history WHERE create_date > (select max(create_date ) from t_excel_media_history_mini);"
