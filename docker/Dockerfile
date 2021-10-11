FROM martinhelmich/typo3:10.4

# Declare the entrypoint.sh file
COPY ./docker/entrypoint.sh /root/entrypoint.sh
RUN chmod +x /root/entrypoint.sh
ENTRYPOINT ["/root/entrypoint.sh"]
