PROGRESS_FILE=/tmp/dependancy_scriptSSH_in_progress
if [ ! -z $1 ]; then
	PROGRESS_FILE=$1
fi
touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}
echo "********************************************************"
echo "*             Installation des dépendances             *"
echo "********************************************************"
apt-get update
echo 50 > ${PROGRESS_FILE}
apt-get install -y php-ssh2
if [ $? -ne 0 ]; then
	apt-get install -y libssh2-php
fi
echo 100 > ${PROGRESS_FILE}
rm ${PROGRESS_FILE}
service apache2 restart
echo "********************************************************"
echo "*             Installation terminée                    *"
echo "********************************************************"